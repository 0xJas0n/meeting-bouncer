<?php

declare(strict_types=1);

namespace App\Screening\Application\Import;

final readonly class RawFocusBlock
{
    public function __construct(public string $id, public \DateTimeImmutable $start, public int $durationMinutes)
    {
    }
}
