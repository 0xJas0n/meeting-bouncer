<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Signal;

/** How often the user went to (override) or liked (rating) earlier meetings with the same title. No flag: it argues for LET_IN. */
final readonly class PastLetInRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        return new Signal('pastLetIn', \count(array_filter($c->pastRatings)));
    }
}
