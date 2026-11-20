<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class AttendeeCountRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $n = $c->invite->attendeeCount()->value;

        return new Signal('attendeeCount', $n, $n > $c->thresholds->warnAttendees
            ? new Flag('many_attendees', sprintf('%d attendees (limit %d).', $n, $c->thresholds->warnAttendees), ['count' => $n, 'limit' => $c->thresholds->warnAttendees])
            : null);
    }
}
