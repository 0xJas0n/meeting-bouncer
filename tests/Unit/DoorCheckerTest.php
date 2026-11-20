<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Domain\DoorCheck\AgendaDetector;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\DoorCheck\Rule\AgendaRule;
use App\Screening\Domain\DoorCheck\Rule\AttendeeCountRule;
use App\Screening\Domain\DoorCheck\Rule\BackToBackRule;
use App\Screening\Domain\DoorCheck\Rule\CostRule;
use App\Screening\Domain\DoorCheck\Rule\DurationRule;
use App\Screening\Domain\DoorCheck\Rule\FocusCollisionRule;
use App\Screening\Domain\DoorCheck\Rule\OptionalAttendeeRule;
use App\Screening\Domain\DoorCheck\Rule\PastLetInRule;
use App\Screening\Domain\DoorCheck\Rule\PastRatingsRule;
use App\Screening\Domain\DoorCheck\Rule\ZombieRecurrenceRule;
use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Service\CostCalculator;
use PHPUnit\Framework\TestCase;

final class DoorCheckerTest extends TestCase
{
    private function checker(): DoorChecker
    {
        $agenda = new AgendaDetector();

        return new DoorChecker([
            new AgendaRule($agenda), new AttendeeCountRule(), new OptionalAttendeeRule(), new CostRule(new CostCalculator()),
            new DurationRule($agenda), new FocusCollisionRule(), new BackToBackRule(), new ZombieRecurrenceRule(), new PastRatingsRule(), new PastLetInRule(),
        ]);
    }

    /** @return list<string> */
    private function codes(DoorCheck $check): array
    {
        return array_map(static fn ($f) => $f->code, $check->flags());
    }

    public function testOffenderQuickSync(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite('sync', 11, 60, true)));

        self::assertSame(66000, $check->cost()->cents, '11 people x 1 h x 60 EUR = 660 EUR');
        self::assertEqualsCanonicalizing(['no_agenda', 'many_attendees', 'optional_in_large_meeting', 'long_without_agenda'], $this->codes($check));
    }

    public function testGoodMeetingHasNoFlags(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite()));

        self::assertSame([], $this->codes($check));
        self::assertSame(12000, $check->cost()->cents);
        self::assertTrue($check->signals()['hasAgenda']);
    }

    public function testZombieSeries(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite('Weekly status round', 9, 30, false, 40)));

        self::assertContains('zombie_series', $this->codes($check));
        self::assertSame(40, $check->signals()['recurringAgeWeeks']);
    }

    public function testRepeatedlyBadRatingsAreFlagged(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), ratings: [false, true, false]));

        self::assertContains('rated_not_worth', $this->codes($check));
        self::assertSame(2, $check->signals()['pastNotWorthIt']);
    }

    public function testMostlyGoodRatingsAreNotFlagged(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), ratings: [false, true, true]));

        self::assertNotContains('rated_not_worth', $this->codes($check));
    }

    public function testPastLetInCountsPositiveFeedback(): void
    {
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(attendees: 30), ratings: [true, true, false]));

        self::assertSame(2, $check->signals()['pastLetIn']);
        self::assertNotContains('rated_not_worth', $this->codes($check));
    }

    public function testFocusCollision(): void
    {
        $focus = [InviteBuilder::slot('2026-11-04 09:30:00', 90)];
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), $focus));

        self::assertContains('focus_collision', $this->codes($check));
    }

    public function testAdjacentFocusBlockIsNoCollision(): void
    {
        $focus = [InviteBuilder::slot('2026-11-04 10:30:00', 60)];

        self::assertNotContains('focus_collision', $this->codes($this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), $focus))));
    }

    public function testThirdConsecutiveMeetingIsFlagged(): void
    {
        $others = [InviteBuilder::slot('2026-11-04 09:00:00', 30), InviteBuilder::slot('2026-11-04 09:30:00', 30)];
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), [], $others));

        self::assertContains('back_to_back', $this->codes($check));
    }

    public function testTwoMeetingsWithBreakAreFine(): void
    {
        $others = [InviteBuilder::slot('2026-11-04 08:30:00', 30), InviteBuilder::slot('2026-11-04 09:30:00', 20)]; // 10 min gap before 10:00
        // the 09:30-09:50 meeting leaves exactly a 10 min break, which is allowed
        self::assertNotContains('back_to_back', $this->codes($this->checker()->check(InviteBuilder::context(InviteBuilder::invite(), [], $others))));
    }

    public function testThresholdsComeFromConfig(): void
    {
        $t = new \App\Screening\Domain\DoorCheck\RuleThresholds(warnAttendees: 3);
        $check = $this->checker()->check(InviteBuilder::context(InviteBuilder::invite(attendees: 4), [], [], $t));

        self::assertContains('many_attendees', $this->codes($check));
    }
}
