<?php

declare(strict_types=1);

namespace App\Screening\Application\ReadModel;

final readonly class VerdictView
{
    /** @param list<string> $flags */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $start,
        public int $durationMinutes,
        public int $attendeeCount,
        public int $costCents,
        public array $flags,
        public string $status,
        public ?string $verdictType,
        public ?string $purpose,
        public ?string $seriousReason,
        public ?string $jokeLine,
        public ?int $suggestedDurationMin,
        public ?string $replyDraft,
        public ?string $asyncTemplate,
        public ?string $persona,
        public bool $canDecide,
        public bool $canRate,
        public ?bool $worthIt,
        public ?string $organizerEmail = null,
    ) {
    }
}
