<?php

declare(strict_types=1);

namespace App\Screening\Application\Query;

final readonly class GetMeetingCost
{
    public function __construct(public string $inviteId)
    {
    }
}
