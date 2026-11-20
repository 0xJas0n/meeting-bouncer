<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

use App\Screening\Domain\Model\VerdictType;

/**
 * Savings assume the organizer follows the verdict: the whole meeting cost is avoided on
 * BOUNCE / GO_ASYNC, the cut part on SHORTEN, nothing on LET_IN.
 */
final class SavingsPolicy
{
    public function calculate(VerdictType $type, int $attendees, int $durationMin, ?int $suggestedMin, int $costCents): Savings
    {
        $savedMinutes = match ($type) {
            VerdictType::LET_IN => 0,
            VerdictType::SHORTEN => max(0, $durationMin - ($suggestedMin ?? $durationMin)),
            VerdictType::GO_ASYNC, VerdictType::BOUNCE => $durationMin,
        };

        return new Savings($savedMinutes * $attendees, (int) round($costCents * $savedMinutes / max(1, $durationMin)));
    }
}
