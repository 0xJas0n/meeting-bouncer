<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

/** Number of invitees including the user. */
final readonly class AttendeeCount
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new \InvalidArgumentException('A meeting has at least one attendee.');
        }
    }
}
