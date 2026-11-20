<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Application\DoorCheck\DoorContextFactory;
use App\Screening\Domain\DoorCheck\DoorChecker;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\InviteNotFound;
use App\Screening\Domain\Port\InviteRepository;
use App\Screening\Domain\Port\VerdictGenerator;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Rules first, LLM second. */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EvaluateInviteHandler
{
    public function __construct(
        private InviteRepository $invites,
        private DoorContextFactory $contexts,
        private DoorChecker $checker,
        private VerdictGenerator $generator,
        private Clock $clock,
    ) {
    }

    public function __invoke(EvaluateInvite $command): void
    {
        $id = new InviteId($command->inviteId);
        $invite = $this->invites->find($id) ?? throw InviteNotFound::withId($id);

        $check = $this->checker->check($this->contexts->forInvite($invite));
        $verdict = $this->generator->generate($invite, $check, $command->persona);

        $invite->evaluate($check, $verdict, $this->clock->now());
        $this->invites->save($invite);
    }
}
