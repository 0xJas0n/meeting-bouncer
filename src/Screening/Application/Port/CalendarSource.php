<?php

declare(strict_types=1);

namespace App\Screening\Application\Port;

use App\Screening\Application\Import\RawFocusBlock;
use App\Screening\Application\Import\RawInvite;

/** Source of raw (NOT yet pseudonymized) calendar data. Adapters: JSON fixtures, ICS, Google, ... */
interface CalendarSource
{
    /** Name used to select the source, e.g. "fixtures" or "google". */
    public function name(): string;

    /** @return iterable<RawInvite> */
    public function invites(): iterable;

    /** @return iterable<RawFocusBlock> */
    public function focusBlocks(): iterable;
}
