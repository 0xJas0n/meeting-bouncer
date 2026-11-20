<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Screening\Domain\Model\InviteId;
use App\Shared\Domain\DomainEvent;

final readonly class MeetingRated implements DomainEvent
{
    public function __construct(public InviteId $inviteId, public bool $worthIt, private \DateTimeImmutable $at)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }
}
