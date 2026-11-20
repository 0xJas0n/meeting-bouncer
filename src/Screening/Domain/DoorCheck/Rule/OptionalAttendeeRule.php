<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class OptionalAttendeeRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $optional = $c->invite->isUserOptional();
        $large = $c->invite->attendeeCount()->value > $c->thresholds->optionalMinAttendees;

        return new Signal('userIsOptional', $optional, $optional && $large
            ? new Flag('optional_in_large_meeting', 'You are optional in a large meeting.')
            : null);
    }
}
