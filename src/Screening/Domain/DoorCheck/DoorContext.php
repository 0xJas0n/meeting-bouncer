<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\TimeSlot;

/** Everything a rule may look at. Rules stay pure functions of this. */
final readonly class DoorContext
{
    /**
     * @param list<TimeSlot> $focusBlocks   focus blocks overlapping the invite's day
     * @param list<TimeSlot> $otherMeetings other meetings on the same day (without the invite itself)
     * @param list<bool> $pastRatings    feedback on earlier invites with the same title: "worth it?" rating, or true for an unrated override
     */
    public function __construct(
        public Invite $invite,
        public \DateTimeImmutable $now,
        public array $focusBlocks,
        public array $otherMeetings,
        public RuleThresholds $thresholds,
        public array $pastRatings = [],
    ) {
    }
}
