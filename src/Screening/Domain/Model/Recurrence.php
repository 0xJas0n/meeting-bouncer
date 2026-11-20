<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class Recurrence
{
    public function __construct(public \DateTimeImmutable $since)
    {
    }

    public function ageInWeeks(\DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $this->since->getTimestamp(), 7 * 86400));
    }
}
