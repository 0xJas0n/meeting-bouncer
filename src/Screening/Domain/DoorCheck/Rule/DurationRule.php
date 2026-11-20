<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck\Rule;

use App\Screening\Domain\DoorCheck\AgendaDetector;
use App\Screening\Domain\DoorCheck\DoorContext;
use App\Screening\Domain\DoorCheck\DoorRule;
use App\Screening\Domain\DoorCheck\Flag;
use App\Screening\Domain\DoorCheck\Signal;

final readonly class DurationRule implements DoorRule
{
    public function __construct(private AgendaDetector $agenda)
    {
    }

    public function evaluate(DoorContext $c): Signal
    {
        $min = $c->invite->duration()->minutes;
        $long = $min >= $c->thresholds->longMeetingMinutes;
        $noAgenda = !$this->agenda->hasAgenda($c->invite->description(), $c->thresholds);

        return new Signal('durationMin', $min, $long && $noAgenda
            ? new Flag('long_without_agenda', sprintf('%d minutes without an agenda.', $min), ['minutes' => $min])
            : null);
    }
}
