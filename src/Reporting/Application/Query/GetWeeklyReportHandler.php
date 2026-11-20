<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query;

use App\Reporting\Application\ReadModel\WeeklyReport;
use App\Reporting\Domain\SavingsLedger;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetWeeklyReportHandler
{
    public function __construct(private SavingsLedger $ledger, private Clock $clock)
    {
    }

    public function __invoke(GetWeeklyReport $query): WeeklyReport
    {
        $now = $this->clock->now();
        $weekStart = $now->modify('monday this week')->setTime(0, 0);

        $minutes = $cents = $worth = $notWorth = 0;
        $counts = [];
        foreach ($this->ledger->decidedBetween($weekStart, $weekStart->modify('+7 days')) as $entry) {
            $minutes += $entry->savings()->personMinutes;
            $cents += $entry->savings()->cents;
            $counts[$entry->outcome()] = ($counts[$entry->outcome()] ?? 0) + 1;
            if (true === $entry->worthIt()) {
                ++$worth;
            } elseif (false === $entry->worthIt()) {
                ++$notWorth;
            }
        }

        return new WeeklyReport($weekStart, $minutes, $cents, $counts, $worth, $notWorth);
    }
}
