<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Application\ReadModel\PrecheckResult;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\RuleThresholds;
use App\Screening\Domain\Model\AttendeeCount;
use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\VerdictGenerator;
use Psr\Log\LoggerInterface;
use App\Screening\Domain\Model\TimeSlot;
use App\Screening\Domain\Model\Title;
use App\Screening\Domain\Privacy\Pseudonymizer;
use App\Screening\Domain\Service\CostCalculator;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Prevention beats rejection: same door check, applied to a draft, answered with concrete fixes. */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class PrecheckDraftHandler
{
    private const LEAN_MINUTES = 30;

    public function __construct(
        private DoorChecker $checker,
        private Pseudonymizer $pseudonymizer,
        private CostCalculator $calculator,
        private RuleThresholds $thresholds,
        private HourlyRateStore $rate,
        private Clock $clock,
        private TranslatorInterface $translator,
        private VerdictGenerator $generator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PrecheckDraft $query): PrecheckResult
    {
        $now = $this->clock->now();
        // no guest list for a draft: names to hide come from the optional field, one per line
        $aliases = array_map(static fn (string $n): array => [$n], array_values(array_filter(array_map('trim', explode("\n", $query->names)), static fn (string $n): bool => '' !== $n)));
        $draft = new Invite(
            new InviteId('draft'),
            Title::fromRedacted($this->pseudonymizer->redact($query->title !== '' ? $query->title : 'Untitled', $aliases)),
            $this->pseudonymizer->redact($query->description, $aliases),
            TimeSlot::startingAt($now->modify('+1 day')->setTime(10, 0), new Duration(max(5, min(480, $query->durationMin)))),
            new AttendeeCount(max(1, min(500, $query->attendeeCount))),
            [],
            false,
            null,
            Language::EN,
        );
        // no calendar knowledge for a draft: focus blocks and neighbours stay empty
        $check = $this->checker->check(new DoorContext($draft, $now, [], [], $this->thresholds->withHourlyRateCents($this->rate->cents())));

        // the rules know nothing about the meeting's purpose, so the same AI read as for real invites runs on the redacted draft
        $verdict = null;
        try {
            $verdict = $this->generator->generate($draft, $check, 'de' === $this->translator->getLocale() ? Persona::BERLIN : Persona::DOORMAN);
        } catch (\Throwable $e) {
            $this->logger->error('Precheck AI read failed', ['exception' => $e::class]); // type only, never content
        }

        $suggestions = $this->suggestions($check, $draft);
        // no rule failed but the AI wants it shorter: still give the user a concrete next step
        if ([] === $suggestions && VerdictType::SHORTEN === $verdict?->type && null !== $verdict->suggestedDurationMin) {
            $suggestions[] = $this->translator->trans('precheck.fix.shorten', ['minutes' => $verdict->suggestedDurationMin]);
        }

        return new PrecheckResult(
            $check->cost()->cents,
            array_map(fn (Flag $f): string => $this->translator->trans('flag.'.$f->code, $f->params), $check->flags()),
            $suggestions,
            [] === $check->flags() && (null === $verdict || VerdictType::LET_IN === $verdict->type),
            $verdict?->seriousReason,
        );
    }

    /** @return list<string> */
    private function suggestions(DoorCheck $check, Invite $draft): array
    {
        $out = [];
        if ($check->hasFlag('no_agenda')) {
            $out[] = $this->translator->trans('precheck.fix.agenda', ['chars' => $this->thresholds->minAgendaChars]);
        }
        if ($check->hasFlag('many_attendees')) {
            $out[] = $this->translator->trans('precheck.fix.attendees', ['limit' => $this->thresholds->warnAttendees]);
        }
        if ($check->hasFlag('long_without_agenda')) {
            $out[] = $this->translator->trans('precheck.fix.duration', ['lean' => self::LEAN_MINUTES, 'minutes' => $draft->duration()->minutes]);
        }
        if ([] !== $out) {
            $lean = $this->calculator->calculate(
                new AttendeeCount($check->hasFlag('many_attendees') ? min($draft->attendeeCount()->value, $this->thresholds->warnAttendees) : $draft->attendeeCount()->value),
                new Duration($check->hasFlag('long_without_agenda') ? min($draft->duration()->minutes, self::LEAN_MINUTES) : $draft->duration()->minutes),
                Money::ofCents($this->rate->cents()),
            );
            if ($lean->cents < $check->cost()->cents) {
                $out[] = $this->translator->trans('precheck.fix.cost', ['lean' => $this->eur($lean->cents), 'now' => $this->eur($check->cost()->cents)]);
            }
        }

        return $out;
    }

    private function eur(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
