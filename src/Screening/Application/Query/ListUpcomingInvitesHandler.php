<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

use App\Screening\Application\DoorCheck\DoorContextFactory;
use App\Screening\Application\ReadModel\InviteSummary;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Port\InviteRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUpcomingInvitesHandler
{
    public function __construct(
        private InviteRepository $invites,
        private Clock $clock,
        private DoorContextFactory $contexts,
        private DoorChecker $checker,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return list<InviteSummary> */
    public function __invoke(ListUpcomingInvites $query): array
    {
        $from = $this->clock->now()->setTime(0, 0);
        $to = $from->modify(sprintf('+%d days', max(1, $query->days)));

        return array_map(function (Invite $i): InviteSummary {
            $check = $this->checker->check($this->contexts->forInvite($i));

            return new InviteSummary(
                $i->id()->value,
                $i->title(),
                $i->slot()->start,
                $i->duration()->minutes,
                $i->attendeeCount()->value,
                $i->status()->value,
                $i->verdict()?->type->value,
                $check->cost()->cents,
                array_map(fn (Flag $f): string => $this->translator->trans('flag.'.$f->code, $f->params), $check->flags()),
                $i->verdict()?->persona->value,
            );
        }, $this->invites->startingBetween($from, $to));
    }
}
