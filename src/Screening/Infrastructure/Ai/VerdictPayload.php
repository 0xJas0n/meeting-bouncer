<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Ai;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * Structured output schema sent to the LLM. Lives in Infrastructure on purpose:
 * the model-facing schema can change without touching the domain Verdict.
 */
final class VerdictPayload
{
    public function __construct(
        #[Schema(description: 'The verdict for this invite.', enum: ['LET_IN', 'SHORTEN', 'GO_ASYNC', 'BOUNCE'])]
        public string $type,
        #[Schema(description: 'What the meeting is for.', enum: ['decision', 'brainstorm', 'status_update', 'info_dump', 'announcement', 'social', 'unclear'])]
        public string $meetingPurpose,
        #[Schema(description: 'One factual sentence explaining the verdict.')]
        public string $seriousReason,
        #[Schema(description: 'One sentence in the persona voice. Funny, never mean.')]
        public string $jokeLine,
        #[Schema(description: 'Suggested duration in minutes when the verdict is SHORTEN, otherwise null.')]
        public ?int $suggestedDurationMin,
        #[Schema(description: 'Polite professional reply to the organizer, ready to send, in the invite language.')]
        public string $replyDraft,
        #[Schema(description: 'Short async update template (bullet points) when the verdict is GO_ASYNC, otherwise null.')]
        public ?string $asyncTemplate,
    ) {
    }
}
