<?php

declare(strict_types=1);

namespace App\Screening\Domain\Port;

use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\Persona;
use App\Screening\Domain\Model\Verdict;

/** Fuzzy part of the decision: purpose, borderline verdicts, wording. Adapters: Symfony AI, rules-only fallback, ... */
interface VerdictGenerator
{
    public function generate(Invite $invite, DoorCheck $check, Persona $persona): Verdict;
}
