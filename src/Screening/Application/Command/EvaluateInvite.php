<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Domain\Model\Persona;

final readonly class EvaluateInvite
{
    public function __construct(public string $inviteId, public Persona $persona = Persona::DOORMAN)
    {
    }
}
