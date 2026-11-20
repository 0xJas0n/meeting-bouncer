<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

/** Protected focus time in the user's calendar. */
class FocusBlock
{
    private string $id;
    private \DateTimeImmutable $startsAt;
    private \DateTimeImmutable $endsAt;

    public function __construct(string $id, TimeSlot $slot)
    {
        $this->id = $id;
        $this->startsAt = $slot->start;
        $this->endsAt = $slot->end;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function slot(): TimeSlot
    {
        return new TimeSlot($this->startsAt, $this->endsAt);
    }
}
