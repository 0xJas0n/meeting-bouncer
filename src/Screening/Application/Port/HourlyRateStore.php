<?php

declare(strict_types=1);

namespace App\Screening\Application\Port;

/** Average hourly cost per attendee, changeable at runtime (dashboard, MCP). Read on every use, so long-running processes see changes. */
interface HourlyRateStore
{
    public function cents(): int;

    public function save(int $cents): void;
}
