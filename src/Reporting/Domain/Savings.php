<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

/** What a decision saved: person-minutes (attendees x minutes) and the matching cost in cents. */
final readonly class Savings
{
    public function __construct(public int $personMinutes, public int $cents)
    {
    }

    public static function none(): self
    {
        return new self(0, 0);
    }
}
