<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

final readonly class ConfirmVerdict
{
    public function __construct(public string $inviteId)
    {
    }
}
