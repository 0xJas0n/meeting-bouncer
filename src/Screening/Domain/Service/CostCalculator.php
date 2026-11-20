<?php

declare(strict_types=1);

namespace App\Screening\Domain\Service;

use App\Screening\Domain\Model\AttendeeCount;
use App\Screening\Domain\Model\Duration;
use App\Shared\Domain\Money;

final class CostCalculator
{
    /** attendees x hours x hourly rate */
    public function calculate(AttendeeCount $attendees, Duration $duration, Money $hourlyRate): Money
    {
        return Money::ofCents((int) round($attendees->value * $duration->minutes * $hourlyRate->cents / 60));
    }
}
