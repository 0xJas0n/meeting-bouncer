<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Application\ReadModel\CostBreakdown;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteNotFound;
use App\Screening\Domain\Port\InviteRepository;
use App\Screening\Domain\Service\CostCalculator;
use App\Shared\Domain\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMeetingCostHandler
{
    public function __construct(private InviteRepository $invites, private CostCalculator $calculator, private HourlyRateStore $rate)
    {
    }

    public function __invoke(GetMeetingCost $query): CostBreakdown
    {
        $id = new InviteId($query->inviteId);
        $invite = $this->invites->find($id) ?? throw InviteNotFound::withId($id);
        $rate = Money::ofCents($this->rate->cents());

        return new CostBreakdown(
            $invite->attendeeCount()->value,
            $invite->duration()->hours(),
            $rate->cents,
            $this->calculator->calculate($invite->attendeeCount(), $invite->duration(), $rate)->cents,
        );
    }
}
