<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

/** A draft invite typed by an organizer, BEFORE it is sent. Free text is pseudonymized like any import. */
final readonly class PrecheckDraft
{
    public function __construct(public string $title, public string $description, public int $attendeeCount, public int $durationMin, public string $names = '')
    {
    }
}
