<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Screening\Domain\Model\InviteId;
use App\Shared\Domain\DomainEvent;

/** The user ignored the bouncer and goes to the meeting anyway. No savings. */
final readonly class VerdictOverridden implements DomainEvent
{
    public function __construct(public InviteId $inviteId, private \DateTimeImmutable $at)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }
}
