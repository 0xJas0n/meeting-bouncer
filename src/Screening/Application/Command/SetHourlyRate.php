<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

final readonly class SetHourlyRate
{
    public function __construct(public float $eur)
    {
    }
}
