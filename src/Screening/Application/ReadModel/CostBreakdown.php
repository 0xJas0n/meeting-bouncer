<?php

declare(strict_types=1);

namespace App\Screening\Application\ReadModel;

final readonly class CostBreakdown
{
    public function __construct(
        public int $attendees,
        public float $hours,
        public int $hourlyRateCents,
        public int $totalCents,
    ) {
    }
}
