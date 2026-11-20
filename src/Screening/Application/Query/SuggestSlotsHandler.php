<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

use App\Screening\Application\ReadModel\SlotView;
use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Model\FocusBlock;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteNotFound;
use App\Screening\Domain\Model\TimeSlot;
use App\Screening\Domain\Port\FocusBlockRepository;
use App\Screening\Domain\Port\InviteRepository;
use App\Screening\Domain\Service\SlotFinder;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class SuggestSlotsHandler
{
    private const HORIZON_DAYS = 14;

    public function __construct(
        private InviteRepository $invites,
        private FocusBlockRepository $focusBlocks,
        private SlotFinder $finder,
        private Clock $clock,
    ) {
    }

    /** @return list<SlotView> */
    public function __invoke(SuggestSlots $query): array
    {
        $id = new InviteId($query->inviteId);
        $this->invites->find($id) ?? throw InviteNotFound::withId($id);

        $from = $this->clock->now();
        $to = $from->modify(sprintf('+%d days', self::HORIZON_DAYS));
        $busy = array_merge(
            // the invite itself is not "busy": moving it may reuse its own slot
            array_map(static fn (Invite $i): TimeSlot => $i->slot(), array_filter($this->invites->startingBetween($from->setTime(0, 0), $to), static fn (Invite $i): bool => !$i->id()->equals($id))),
            array_map(static fn (FocusBlock $b): TimeSlot => $b->slot(), $this->focusBlocks->overlapping($from->setTime(0, 0), $to)),
        );

        return array_map(
            static fn (TimeSlot $s): SlotView => new SlotView($s->start, $s->end),
            $this->finder->find(new Duration(max(5, $query->durationMin)), $from, $busy),
        );
    }
}
