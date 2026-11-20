<?php

declare(strict_types=1);

namespace App\Reporting\Application\Projector;

use App\Screening\Domain\Event\AllInvitesRemoved;
use App\Reporting\Domain\SavingsEntry;
use App\Reporting\Domain\SavingsLedger;
use App\Reporting\Domain\SavingsPolicy;
use App\Screening\Domain\Event\MeetingRated;
use App\Screening\Domain\Event\VerdictConfirmed;
use App\Screening\Domain\Event\VerdictOverridden;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Reacts to Screening's domain events; Screening never knows Reporting exists. */
final readonly class SavingsProjector
{
    public function __construct(private SavingsLedger $ledger, private SavingsPolicy $policy)
    {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onConfirmed(VerdictConfirmed $e): void
    {
        $savings = $this->policy->calculate($e->type, $e->attendeeCount, $e->durationMin, $e->suggestedDurationMin, $e->costCents);
        $this->ledger->record(new SavingsEntry($e->inviteId->value, $e->occurredAt(), $e->type->value, $savings->personMinutes, $savings->cents));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onOverridden(VerdictOverridden $e): void
    {
        $this->ledger->record(new SavingsEntry($e->inviteId->value, $e->occurredAt(), 'OVERRIDDEN', 0, 0));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onAllInvitesRemoved(AllInvitesRemoved $e): void
    {
        $this->ledger->clear();
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onRated(MeetingRated $e): void
    {
        $entry = $this->ledger->find($e->inviteId->value);
        if (null !== $entry) {
            $entry->rate($e->worthIt);
            $this->ledger->save($entry);
        }
    }
}
