<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class FocusCollisionRule implements DoorRule
{
    public function evaluate(DoorContext $c): Signal
    {
        $slot = $c->invite->slot();
        foreach ($c->focusBlocks as $block) {
            if ($slot->overlaps($block)) {
                return new Signal('collidesWithFocus', true, new Flag('focus_collision', 'Overlaps a protected focus block.'));
            }
        }

        return new Signal('collidesWithFocus', false);
    }
}
