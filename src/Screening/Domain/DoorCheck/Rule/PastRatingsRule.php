<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

/** The user's own earlier "was it worth it?" answers for meetings with the same title. */
final readonly class PastRatingsRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $bad = \count(array_filter($c->pastRatings, static fn (bool $worth): bool => !$worth));
        $good = \count($c->pastRatings) - $bad;

        return new Signal('pastNotWorthIt', $bad, $bad >= $c->thresholds->minBadRatings && $bad > $good
            ? new Flag('rated_not_worth', sprintf('You rated earlier meetings with this title as not worth it (%d of %d).', $bad, $bad + $good), ['notWorth' => $bad, 'worth' => $good])
            : null);
    }
}
