<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

/** Tunable limits of the door check. Built from config/packages/bouncer.yaml; the hourly rate is set at runtime (HourlyRateStore). */
final readonly class RuleThresholds
{
    /** @param list<string> $genericAgendas descriptions that carry no information */
    public function __construct(
        public int $hourlyRateCents = 6000,
        public int $minAgendaChars = 40,
        public int $warnAttendees = 8,
        public int $optionalMinAttendees = 6,
        public int $longMeetingMinutes = 60,
        public int $breakMinutes = 10,
        public int $zombieWeeks = 12,
        public int $minBadRatings = 2,
        public array $genericAgendas = ['sync', 'catch-up', 'catch up', 'quick call', 'quick sync', 'status', 'weekly status round', 'call', 'meeting', 'besprechung'],
    ) {
    }

    public function withHourlyRateCents(int $cents): self
    {
        return new self(...['hourlyRateCents' => $cents] + get_object_vars($this));
    }
}
