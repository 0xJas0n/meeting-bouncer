<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class ZombieRecurrenceRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $weeks = $c->invite->recurrence()?->ageInWeeks($c->now);

        return new Signal('recurringAgeWeeks', $weeks, null !== $weeks && $weeks > $c->thresholds->zombieWeeks
            ? new Flag('zombie_series', sprintf('Recurring series running for %d weeks.', $weeks), ['weeks' => $weeks])
            : null);
    }
}
