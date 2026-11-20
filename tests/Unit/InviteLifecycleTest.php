<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Event\MeetingRated;
use App\Screening\Domain\Event\VerdictConfirmed;
use App\Screening\Domain\Model\InviteStatus;
use App\Screening\Domain\Model\MeetingPurpose;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;
use App\Screening\Domain\Model\VerdictType;
use PHPUnit\Framework\TestCase;

final class InviteLifecycleTest extends TestCase
{
    private function verdict(VerdictType $t): Verdict
    {
        return new Verdict($t, MeetingPurpose::DECISION, 'r', 'j', VerdictType::SHORTEN === $t ? 45 : null, 'reply', null, Persona::DOORMAN);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(InviteBuilder::NOW);
    }

    public function testConfirmRecordsEventWithSavingsInputs(): void
    {
        $invite = InviteBuilder::invite(attendees: 7, minutes: 90);
        $invite->evaluate(new DoorCheck([]), $this->verdict(VerdictType::SHORTEN), $this->now());
        $invite->pullDomainEvents();

        $invite->confirm($this->now());
        $events = $invite->pullDomainEvents();

        self::assertSame(InviteStatus::CONFIRMED, $invite->status());
        self::assertInstanceOf(VerdictConfirmed::class, $events[0]);
        self::assertSame([7, 90, 45], [$events[0]->attendeeCount, $events[0]->durationMin, $events[0]->suggestedDurationMin]);
    }

    public function testCannotConfirmBeforeEvaluation(): void
    {
        $this->expectException(\DomainException::class);
        InviteBuilder::invite()->confirm($this->now());
    }

    public function testDecidedInviteIsFinal(): void
    {
        $invite = InviteBuilder::invite();
        $invite->evaluate(new DoorCheck([]), $this->verdict(VerdictType::BOUNCE), $this->now());
        $invite->confirm($this->now());

        $this->expectException(\DomainException::class);
        $invite->evaluate(new DoorCheck([]), $this->verdict(VerdictType::LET_IN), $this->now());
    }

    public function testRatingOnlyAfterAttendedMeetingAndOnlyOnce(): void
    {
        $invite = InviteBuilder::invite();
        $invite->evaluate(new DoorCheck([]), $this->verdict(VerdictType::LET_IN), $this->now());
        try {
            $invite->rate(true, $this->now());
            self::fail('rating before confirming must fail');
        } catch (\DomainException) {
        }
        $invite->confirm($this->now());
        $invite->pullDomainEvents();
        $invite->rate(true, $this->now());

        self::assertInstanceOf(MeetingRated::class, $invite->pullDomainEvents()[0]);
        $this->expectException(\DomainException::class);
        $invite->rate(false, $this->now());
    }

    public function testBouncedMeetingCannotBeRated(): void
    {
        $invite = InviteBuilder::invite();
        $invite->evaluate(new DoorCheck([]), $this->verdict(VerdictType::BOUNCE), $this->now());
        $invite->confirm($this->now());

        $this->expectException(\DomainException::class);
        $invite->rate(true, $this->now());
    }
}
