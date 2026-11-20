<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Signal;
use App\Screening\Domain\Service\CostCalculator;
use App\Shared\Domain\Money;

final readonly class CostRule implements DoorRule
{
    public function __construct(private CostCalculator $calculator)
    {
    }

    public function evaluate(DoorContext $c): Signal
    {
        $cost = $this->calculator->calculate(
            $c->invite->attendeeCount(),
            $c->invite->duration(),
            Money::ofCents($c->thresholds->hourlyRateCents),
        );

        return new Signal('costCents', $cost->cents);
    }
}
