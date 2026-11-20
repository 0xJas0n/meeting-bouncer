<?php

declare(strict_types=1);

namespace App\Screening\Application\ReadModel;

final readonly class InviteSummary
{
    /** @param list<string> $flags human-readable door check flags */
    public function __construct(
        public string $id,
        public string $title,
        public \DateTimeImmutable $start,
        public int $durationMinutes,
        public int $attendeeCount,
        public string $status,
        public ?string $verdictType,
        public int $costCents,
        public array $flags,
        public ?string $reviewedBy = null,
    ) {
    }
}
