<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\VerdictType;
use App\Shared\Domain\DomainEvent;

final readonly class InviteEvaluated implements DomainEvent
{
    public function __construct(public InviteId $inviteId, public VerdictType $type, private \DateTimeImmutable $at)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }
}
