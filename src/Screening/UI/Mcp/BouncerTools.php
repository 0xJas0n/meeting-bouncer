<?php

declare(strict_types=1);

namespace App\Screening\UI\Mcp;

use App\Reporting\Application\Query\GetWeeklyReport;
use App\Reporting\Application\ReadModel\WeeklyReport;
use App\Reporting\Domain\SavingsPolicy;
use App\Screening\Application\Command\ConfirmVerdict;
use App\Screening\Application\Command\EvaluateInvite;
use App\Screening\Application\Command\OverrideVerdict;
use App\Screening\Application\Command\RateMeeting;
use App\Screening\Application\Command\SetHourlyRate;
use App\Screening\Application\Query\GetInviteVerdict;
use App\Screening\Application\Query\GetMeetingCost;
use App\Screening\Application\Query\ListUpcomingInvites;
use App\Screening\Application\Query\PrecheckDraft;
use App\Screening\Application\Query\SuggestSlots;
use App\Screening\Application\ReadModel\CostBreakdown;
use App\Screening\Application\ReadModel\InviteSummary;
use App\Screening\Application\ReadModel\PrecheckResult;
use App\Screening\Application\ReadModel\SlotView;
use App\Screening\Application\ReadModel\VerdictView;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\VerdictType;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ResourceReadException;
use Mcp\Exception\ToolCallException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * MCP adapter: maps tool calls to the same commands/queries the web UI uses, no logic of its own.
 * Returns pseudonymized read models only. Never sends or changes anything in a real calendar.
 */
final readonly class BouncerTools
{
    public function __construct(private QueryBus $queries, private CommandBus $commands, private SavingsPolicy $savings, private TranslatorInterface $translator)
    {
    }

    /** @return list<array<string, mixed>> */
    #[McpTool(name: 'list_upcoming_invites', description: 'List upcoming calendar invites (pseudonymized) with their current verdict, if any.')]
    public function listUpcomingInvites(#[Schema(minimum: 1, maximum: 31)] int $days = 7): array
    {
        /** @var list<InviteSummary> $invites */
        $invites = $this->send(new ListUpcomingInvites($days));

        return array_map($this->summary(...), $invites);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'check_meeting', description: 'Let the bouncer judge one invite. persona: DOORMAN, NEW_YORKER, SURFER, PIRATE, SHERIFF (English) or BERLIN, WIENER_GRANT, SCHWABE, KOELNER, BAYER (German). Returns verdict, joke, reason and a polite reply draft.')]
    public function checkMeeting(string $inviteId, string $persona = 'DOORMAN'): array
    {
        $persona = $this->persona($persona);
        $note = null;
        try {
            $this->run(new EvaluateInvite($inviteId, $persona));
        } catch (ToolCallException $e) {
            // e.g. already confirmed: still show the stored verdict
            $note = $e->getMessage();
        }

        return $this->verdict($this->view($inviteId)) + (null === $note ? [] : ['note' => $note]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'check_week', description: 'Check all upcoming invites of the week. Only invites without a verdict are reviewed with the given persona; use check_meeting to re-review one. Returns every verdict plus totals (counts per verdict, hours and EUR that could be saved).')]
    public function checkWeek(string $persona = 'DOORMAN'): array
    {
        $persona = $this->persona($persona);
        /** @var list<InviteSummary> $invites */
        $invites = $this->send(new ListUpcomingInvites(7));

        $verdicts = $counts = [];
        $personMinutes = $cents = 0;
        foreach ($invites as $invite) {
            if ('pending' === $invite->status) {
                $this->run(new EvaluateInvite($invite->id, $persona));
            }
            $view = $this->view($invite->id);
            $verdicts[] = $this->verdict($view);
            if (null !== $view->verdictType) {
                $counts[$view->verdictType] = ($counts[$view->verdictType] ?? 0) + 1;
                $s = $this->savings->calculate(VerdictType::from($view->verdictType), $view->attendeeCount, $view->durationMinutes, $view->suggestedDurationMin, $view->costCents);
                $personMinutes += $s->personMinutes;
                $cents += $s->cents;
            }
        }

        return [
            'verdicts' => $verdicts,
            'totals' => [
                'invites' => \count($invites),
                'countsPerVerdict' => (object) $counts,
                'potentialHoursSaved' => round($personMinutes / 60, 1),
                'potentialEurSaved' => $cents / 100,
            ],
        ];
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'meeting_cost', description: 'Cost of an invite in EUR with breakdown (attendees x hours x hourly rate).')]
    public function meetingCost(string $inviteId): array
    {
        $c = $this->ask(CostBreakdown::class, new GetMeetingCost($inviteId));

        return [
            'attendees' => $c->attendees,
            'hours' => $c->hours,
            'hourlyRateEur' => $c->hourlyRateCents / 100,
            'totalEur' => $c->totalCents / 100,
        ];
    }

    /** @return array<string, float> */
    #[McpTool(name: 'set_hourly_rate', description: 'Set the average hourly cost per attendee in EUR used for all meeting costs from now on. Savings already confirmed keep their old cost. Changes local settings only.')]
    public function setHourlyRate(#[Schema(minimum: 1, maximum: 1000)] float $hourlyRateEur): array
    {
        $this->run(new SetHourlyRate($hourlyRateEur));

        return ['hourlyRateEur' => $hourlyRateEur];
    }

    /** @return list<array<string, string>> */
    #[McpTool(name: 'suggest_slot', description: 'Up to 3 free weekday slots (08:00-18:00, avoiding meetings and focus blocks) for a meeting of the given length.')]
    public function suggestSlot(string $inviteId, #[Schema(minimum: 5, maximum: 480)] int $durationMin): array
    {
        /** @var list<SlotView> $slots */
        $slots = $this->send(new SuggestSlots($inviteId, $durationMin));

        return array_map(static fn (SlotView $s): array => ['start' => $s->start->format('d.m.Y H:i'), 'end' => $s->end->format('d.m.Y H:i')], $slots);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'precheck_draft', description: 'Check a DRAFT invite before it is sent and get concrete fixes. Prevention beats rejection.')]
    public function precheckDraft(string $title, string $description, #[Schema(minimum: 1, maximum: 500)] int $attendeeCount, #[Schema(minimum: 5, maximum: 480)] int $durationMin): array
    {
        $r = $this->ask(PrecheckResult::class, new PrecheckDraft($title, $description, $attendeeCount, $durationMin));

        return ['ready' => $r->ready, 'costEur' => $r->costCents / 100, 'flags' => $r->flags, 'suggestions' => $r->suggestions];
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'weekly_report', description: 'Person-hours (guests x hours) and EUR saved this week by confirmed verdicts, counts per outcome, and "worth it" ratings. Aggregated, never per person.')]
    public function weeklyReport(): array
    {
        $r = $this->ask(WeeklyReport::class, new GetWeeklyReport());

        return [
            'weekStart' => $r->weekStart->format('d.m.Y'),
            'hoursSaved' => $r->hoursSaved(),
            'eurSaved' => $r->centsSaved / 100,
            'countsPerOutcome' => (object) $r->countsByOutcome,
            'ratedWorthIt' => $r->ratedWorthIt,
            'ratedNotWorthIt' => $r->ratedNotWorthIt,
        ];
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'confirm_verdict', description: 'Accept the bouncer verdict for an invite. Only confirmed verdicts count towards the weekly savings. Changes local data only, never the real calendar.')]
    public function confirmVerdict(string $inviteId): array
    {
        $this->run(new ConfirmVerdict($inviteId));

        return $this->verdict($this->view($inviteId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'override_verdict', description: 'Reject the bouncer verdict for an invite (user goes anyway). Changes local data only, never the real calendar.')]
    public function overrideVerdict(string $inviteId): array
    {
        $this->run(new OverrideVerdict($inviteId));

        return $this->verdict($this->view($inviteId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'rate_meeting', description: 'Rate a meeting afterwards: was it worth it? Feeds the "worth it" ratio of the weekly report.')]
    public function rateMeeting(string $inviteId, bool $worthIt): array
    {
        $this->run(new RateMeeting($inviteId, $worthIt));

        return $this->verdict($this->view($inviteId));
    }

    #[McpResourceTemplate(uriTemplate: 'bouncer://invite/{inviteId}', name: 'invite-verdict', description: 'One invite (pseudonymized) with its flags and stored verdict.', mimeType: 'application/json')]
    public function invite(string $inviteId): string
    {
        try {
            return json_encode($this->verdict($this->view($inviteId)), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (ToolCallException $e) {
            throw new ResourceReadException($e->getMessage(), 0, $e);
        }
    }

    /** Domain and input errors reach the MCP client as a readable tool error, not as "Error while executing tool". */
    private function send(object $query): mixed
    {
        try {
            return $this->queries->ask($query);
        } catch (\DomainException|\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        }
    }

    private function run(object $command): void
    {
        try {
            $this->commands->dispatch($command);
        } catch (\DomainException|\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        }
    }

    private function persona(string $value): Persona
    {
        try {
            return Persona::fromInput($value);
        } catch (\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage(), 0, $e);
        }
    }

    private function view(string $inviteId): VerdictView
    {
        return $this->ask(VerdictView::class, new GetInviteVerdict($inviteId));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function ask(string $type, object $query): object
    {
        $result = $this->send($query);

        return $result instanceof $type ? $result : throw new \LogicException(sprintf('Query %s did not return %s.', $query::class, $type));
    }

    /** Display text for a verdict, from the same tick.* labels the web UI uses (default locale). */
    private function label(?string $verdictType): ?string
    {
        return null === $verdictType ? null : $this->translator->trans('tick.'.$verdictType);
    }

    /** @return array<string, mixed> */
    private function summary(InviteSummary $i): array
    {
        return [
            'id' => $i->id,
            'title' => $i->title,
            'start' => $i->start->format('d.m.Y H:i'),
            'durationMin' => $i->durationMinutes,
            'attendeeCount' => $i->attendeeCount,
            'costEur' => $i->costCents / 100,
            'status' => $i->status,
            'verdict' => $i->verdictType,
            'verdictLabel' => $this->label($i->verdictType),
        ];
    }

    /** @return array<string, mixed> */
    private function verdict(VerdictView $v): array
    {
        return [
            'id' => $v->id,
            'title' => $v->title,
            'start' => $v->start->format('d.m.Y H:i'),
            'durationMin' => $v->durationMinutes,
            'attendeeCount' => $v->attendeeCount,
            'costEur' => $v->costCents / 100,
            'flags' => $v->flags,
            'status' => $v->status,
            'verdict' => $v->verdictType,
            'verdictLabel' => $this->label($v->verdictType),
            'purpose' => $v->purpose,
            'persona' => $v->persona,
            'jokeLine' => $v->jokeLine,
            'seriousReason' => $v->seriousReason,
            'suggestedDurationMin' => $v->suggestedDurationMin,
            'replyDraft' => null === $v->replyDraft ? null : trim(str_replace('{sender}', '', $v->replyDraft)),
            'asyncTemplate' => $v->asyncTemplate,
        ];
    }
}
