<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\RuleThresholds;
use App\Screening\Domain\Model\AttendeeCount;
use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Model\RedactedText;
use App\Screening\Domain\Model\Recurrence;
use App\Screening\Domain\Model\TimeSlot;
use App\Screening\Domain\Model\Title;

/** Test helper: a Wednesday 10:00 invite with sensible defaults. */
final class InviteBuilder
{
    public const NOW = '2026-11-04 08:00:00';

    public static function invite(
        string $description = 'Decide the Q1 pricing tiers and name an owner for the rollout.',
        int $attendees = 4,
        int $minutes = 30,
        bool $optional = false,
        ?int $recurringWeeks = null,
        string $start = '2026-11-04 10:00:00',
        string $id = 'inv-test',
    ): Invite {
        return new Invite(
            new InviteId($id),
            Title::fromRedacted(new RedactedText('Test meeting')),
            new RedactedText($description),
            TimeSlot::startingAt(new \DateTimeImmutable($start), new Duration($minutes)),
            new AttendeeCount($attendees),
            [],
            $optional,
            null === $recurringWeeks ? null : new Recurrence((new \DateTimeImmutable(self::NOW))->modify("-$recurringWeeks weeks")),
            Language::EN,
        );
    }

    /**
     * @param list<TimeSlot> $focus
     * @param list<TimeSlot> $others
     * @param list<bool> $ratings
     */
    public static function context(Invite $invite, array $focus = [], array $others = [], ?RuleThresholds $t = null, array $ratings = []): DoorContext
    {
        return new DoorContext($invite, new \DateTimeImmutable(self::NOW), $focus, $others, $t ?? new RuleThresholds(), $ratings);
    }

    public static function slot(string $start, int $minutes): TimeSlot
    {
        return TimeSlot::startingAt(new \DateTimeImmutable($start), new Duration($minutes));
    }
}
