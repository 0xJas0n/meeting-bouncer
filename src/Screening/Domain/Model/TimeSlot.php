<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class TimeSlot
{
    public function __construct(public \DateTimeImmutable $start, public \DateTimeImmutable $end)
    {
        if ($end <= $start) {
            throw new \InvalidArgumentException('TimeSlot end must be after start.');
        }
    }

    public static function startingAt(\DateTimeImmutable $start, Duration $duration): self
    {
        return new self($start, $start->modify(sprintf('+%d minutes', $duration->minutes)));
    }

    public function duration(): Duration
    {
        return new Duration((int) (($this->end->getTimestamp() - $this->start->getTimestamp()) / 60));
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
