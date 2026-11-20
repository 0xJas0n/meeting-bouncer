<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteNotFound;
use App\Screening\Domain\Port\InviteRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RateMeetingHandler
{
    public function __construct(private InviteRepository $invites, private Clock $clock)
    {
    }

    public function __invoke(RateMeeting $command): void
    {
        $id = new InviteId($command->inviteId);
        $invite = $this->invites->find($id) ?? throw InviteNotFound::withId($id);
        $invite->rate($command->worthIt, $this->clock->now());
        $this->invites->save($invite);
    }
}
