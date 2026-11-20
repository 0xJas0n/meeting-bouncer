<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

final readonly class ListUpcomingInvites
{
    public function __construct(public int $days = 7)
    {
    }
}
