<?php

declare(strict_types=1);

namespace App\Screening\Application\ReadModel;

final readonly class SlotView
{
    public function __construct(public \DateTimeImmutable $start, public \DateTimeImmutable $end)
    {
    }
}
