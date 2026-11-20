<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

/** Flags the third (or later) consecutive meeting without a break. */
final readonly class BackToBackRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $gap = $c->thresholds->breakMinutes * 60;
        $chain = 1;
        $cursor = $c->invite->slot()->start->getTimestamp();
        do {
            $found = false;
            foreach ($c->otherMeetings as $m) {
                $end = $m->end->getTimestamp();
                if ($end <= $cursor && $cursor - $end < $gap) {
                    ++$chain;
                    $cursor = $m->start->getTimestamp();
                    $found = true;
                    break;
                }
            }
        } while ($found);

        return new Signal('backToBack', $chain >= 3, $chain >= 3
            ? new Flag('back_to_back', sprintf('Meeting number %d in a row without a %d min break.', $chain, $c->thresholds->breakMinutes), ['chain' => $chain, 'minutes' => $c->thresholds->breakMinutes])
            : null);
    }
}
