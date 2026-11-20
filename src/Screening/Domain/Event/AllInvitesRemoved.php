<?php

declare(strict_types=1);

namespace App\Screening\Domain\Event;

use App\Shared\Domain\DomainEvent;

/** Every invite (and with it every decision) was deleted, e.g. on a demo reset. */
final readonly class AllInvitesRemoved implements DomainEvent
{
    public function __construct(private \DateTimeImmutable $at)
    {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }
}
