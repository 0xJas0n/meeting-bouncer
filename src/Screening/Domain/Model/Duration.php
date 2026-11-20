<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class Duration
{
    public function __construct(public int $minutes)
    {
        if ($minutes <= 0) {
            throw new \InvalidArgumentException('Duration must be positive.');
        }
    }

    public function hours(): float
    {
        return $this->minutes / 60;
    }
}
