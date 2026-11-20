<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\AgendaDetector;
use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class AgendaRule implements DoorRule
{
    public function __construct(private AgendaDetector $agenda)
    {
    }

    public function evaluate(DoorContext $c): Signal
    {
        $has = $this->agenda->hasAgenda($c->invite->description(), $c->thresholds);

        return new Signal('hasAgenda', $has, $has ? null : new Flag('no_agenda', 'No real agenda in the description.'));
    }
}
