<?php

declare(strict_types=1);

namespace App\Screening\Domain\Service;

use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Model\TimeSlot;

final class SlotFinder
{
    private const DAY_START_HOUR = 8;
    private const DAY_END_HOUR = 18;
    private const STEP_MINUTES = 30;
    private const MIN_SPREAD_MINUTES = 120;

    /**
     * Up to $limit free weekday slots after $from, at least 2 h apart so the options are real alternatives.
     *
     * @param list<TimeSlot> $busy meetings and focus blocks to avoid
     *
     * @return list<TimeSlot>
     */
    public function find(Duration $duration, \DateTimeImmutable $from, array $busy, int $limit = 3, int $searchDays = 7): array
    {
        $found = [];
        for ($d = 0; $d < $searchDays && \count($found) < $limit; ++$d) {
            $day = $from->modify("+$d days")->setTime(0, 0);
            if ($day->format('N') >= 6) {
                continue;
            }
            $cursor = $day->setTime(self::DAY_START_HOUR, 0);
            $dayEnd = $day->setTime(self::DAY_END_HOUR, 0);
            for (; $cursor->modify("+{$duration->minutes} minutes") <= $dayEnd && \count($found) < $limit; $cursor = $cursor->modify('+'.self::STEP_MINUTES.' minutes')) {
                if ($cursor < $from) {
                    continue;
                }
                $slot = TimeSlot::startingAt($cursor, $duration);
                if ($this->isFree($slot, $busy) && $this->farFromFound($slot, $found)) {
                    $found[] = $slot;
                }
            }
        }

        return $found;
    }

    /** @param list<TimeSlot> $busy */
    private function isFree(TimeSlot $slot, array $busy): bool
    {
        foreach ($busy as $b) {
            if ($slot->overlaps($b)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<TimeSlot> $found */
    private function farFromFound(TimeSlot $slot, array $found): bool
    {
        foreach ($found as $f) {
            if (abs($slot->start->getTimestamp() - $f->start->getTimestamp()) < self::MIN_SPREAD_MINUTES * 60) {
                return false;
            }
        }

        return true;
    }
}
