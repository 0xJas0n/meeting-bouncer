<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

/** One signal per rule. Add a class and tag it `app.door_rule` (done via _instanceof) to extend the check. */
interface DoorRule
{
    public function evaluate(DoorContext $context): Signal;
}
