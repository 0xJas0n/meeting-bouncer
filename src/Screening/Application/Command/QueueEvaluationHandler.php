<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Domain\Model\InviteNotFound;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class QueueEvaluationHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(QueueEvaluation $message): void
    {
        try {
            $this->commands->dispatch(new EvaluateInvite($message->inviteId, $message->persona));
        } catch (InviteNotFound|\DomainException $e) {
            // already decided or gone: retrying cannot help. LLM/network errors still retry.
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }
    }
}
