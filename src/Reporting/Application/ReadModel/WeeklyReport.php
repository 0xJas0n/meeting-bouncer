<?php

declare(strict_types=1);

namespace App\Reporting\Application\ReadModel;

final readonly class WeeklyReport
{
    /** @param array<string, int> $countsByOutcome e.g. GO_ASYNC => 2, OVERRIDDEN => 1 */
    public function __construct(
        public \DateTimeImmutable $weekStart,
        public int $personMinutesSaved,
        public int $centsSaved,
        public array $countsByOutcome,
        public int $ratedWorthIt,
        public int $ratedNotWorthIt,
    ) {
    }

    public function hoursSaved(): float
    {
        return round($this->personMinutesSaved / 60, 1);
    }
}
