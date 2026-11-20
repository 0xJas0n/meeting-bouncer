<?php

declare(strict_types=1);

namespace App\Screening\Application\DoorCheck;

use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\RuleThresholds;
use App\Screening\Domain\Model\FocusBlock;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Port\FocusBlockRepository;
use App\Screening\Domain\Port\InviteRepository;
use App\Shared\Domain\Clock;

/** Gathers the calendar neighbourhood of an invite so the pure rules can run. */
final readonly class DoorContextFactory
{
    public function __construct(
        private InviteRepository $invites,
        private FocusBlockRepository $focusBlocks,
        private RuleThresholds $thresholds,
        private Clock $clock,
        private HourlyRateStore $rate,
    ) {
    }

    public function forInvite(Invite $invite): DoorContext
    {
        $dayStart = $invite->slot()->start->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $others = [];
        foreach ($this->invites->startingBetween($dayStart, $dayEnd) as $other) {
            if (!$other->id()->equals($invite->id())) {
                $others[] = $other->slot();
            }
        }

        return new DoorContext(
            $invite,
            $this->clock->now(),
            array_map(static fn (FocusBlock $b) => $b->slot(), $this->focusBlocks->overlapping($dayStart, $dayEnd)),
            $others,
            $this->thresholds->withHourlyRateCents($this->rate->cents()),
            $this->invites->feedbackForTitle($invite->title(), $invite->id()),
        );
    }
}
