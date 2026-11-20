<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Domain\Model\Persona;

/** Background variant of EvaluateInvite: routed to the `async` transport, one message per invite so workers run them in parallel. */
final readonly class QueueEvaluation
{
    public function __construct(public string $inviteId, public Persona $persona = Persona::DOORMAN)
    {
    }
}
