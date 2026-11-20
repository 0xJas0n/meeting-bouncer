<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Ai;

use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\MeetingPurpose;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;
use App\Screening\Domain\Model\VerdictType;
use App\Screening\Domain\Port\VerdictGenerator;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** The single place that talks to an LLM. Swap provider via config/packages/ai.yaml or replace this adapter. */
final readonly class SymfonyAiVerdictGenerator implements VerdictGenerator
{
    public function __construct(
        #[Autowire(service: 'ai.agent.bouncer')]
        private AgentInterface $agent,
        private PromptBuilder $prompts,
        private LoggerInterface $logger,
    ) {
    }

    public function generate(Invite $invite, DoorCheck $check, Persona $persona): Verdict
    {
        $result = $this->agent->call($this->prompts->messages($invite, $check, $persona), ['response_format' => VerdictPayload::class]);
        $content = $result->getContent();
        // Reasoning models (a `-latest` alias can switch to one anytime) return [ThinkingResult, ObjectResult]; getContent() is then an array.
        if (\is_array($content)) {
            $content = array_find($content, static fn ($part) => $part instanceof ObjectResult)?->getContent() ?? $content;
        }

        if (!$content instanceof VerdictPayload) {
            // GDPR: type only, never content.
            throw new \UnexpectedValueException(sprintf('Platform did not return a structured VerdictPayload (result %s, content %s).', $result::class, get_debug_type($content)));
        }

        $verdict = $this->toDomain($content, $persona);
        // GDPR: log ids and verdict types only, never invite content.
        $this->logger->info('Invite evaluated', ['invite' => $invite->id()->value, 'verdict' => $verdict->type->value]);

        return $verdict;
    }

    private function toDomain(VerdictPayload $p, Persona $persona): Verdict
    {
        $type = VerdictType::tryFrom($p->type) ?? throw new \UnexpectedValueException(sprintf('Unknown verdict type "%s".', $p->type));
        $purpose = MeetingPurpose::tryFrom($p->meetingPurpose) ?? MeetingPurpose::UNCLEAR;

        return new Verdict(
            $type,
            $purpose,
            $p->seriousReason,
            $p->jokeLine,
            VerdictType::SHORTEN === $type ? $p->suggestedDurationMin : null,
            $p->replyDraft,
            VerdictType::GO_ASYNC === $type ? $p->asyncTemplate : null,
            $persona,
        );
    }
}
