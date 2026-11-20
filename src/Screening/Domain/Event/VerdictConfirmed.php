<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\VerdictType;
use App\Shared\Domain\DomainEvent;

/** The user accepted the verdict. Carries everything Reporting needs, so it never queries Screening. */
final readonly class VerdictConfirmed implements DomainEvent
{
    public function __construct(
        public InviteId $inviteId,
        public VerdictType $type,
        public int $attendeeCount,
        public int $durationMin,
        public ?int $suggestedDurationMin,
        public int $costCents,
        private \DateTimeImmutable $at,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }
}
