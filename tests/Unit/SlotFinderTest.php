<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Service\SlotFinder;
use PHPUnit\Framework\TestCase;

final class SlotFinderTest extends TestCase
{
    public function testFindsThreeSpreadSlotsAndAvoidsBusyTimes(): void
    {
        $busy = [InviteBuilder::slot('2026-11-04 08:00:00', 120)];
        $slots = (new SlotFinder())->find(new Duration(30), new \DateTimeImmutable('2026-11-04 08:00:00'), $busy);

        self::assertCount(3, $slots);
        self::assertSame('10:00', $slots[0]->start->format('H:i'));
        foreach ($slots as $s) {
            foreach ($busy as $b) {
                self::assertFalse($s->overlaps($b));
            }
        }
    }

    public function testSkipsWeekends(): void
    {
        $slots = (new SlotFinder())->find(new Duration(60), new \DateTimeImmutable('2026-11-07 09:00:00'), [], 1); // Saturday

        self::assertSame('2026-11-09', $slots[0]->start->format('Y-m-d'));
    }
}
