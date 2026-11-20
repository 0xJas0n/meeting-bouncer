<?php

declare(strict_types=1);

namespace App\Screening\Application\Import;

/** Raw personal data. Must never leave the import boundary. */
final readonly class RawAttendee
{
    public function __construct(public string $name, public string $email, public bool $isSelf = false, public bool $optional = false)
    {
    }
}
