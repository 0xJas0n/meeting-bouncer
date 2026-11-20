<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

/** One decided invite. Aggregated per user/team only: it deliberately has no organizer or attendee field. */
class SavingsEntry
{
    private ?bool $worthIt = null;

    public function __construct(
        private string $inviteId,
        private \DateTimeImmutable $decidedAt,
        private string $outcome,
        private int $personMinutes,
        private int $cents,
    ) {
    }

    public function inviteId(): string
    {
        return $this->inviteId;
    }

    public function decidedAt(): \DateTimeImmutable
    {
        return $this->decidedAt;
    }

    /** VerdictType value, or "OVERRIDDEN". */
    public function outcome(): string
    {
        return $this->outcome;
    }

    public function savings(): Savings
    {
        return new Savings($this->personMinutes, $this->cents);
    }

    public function worthIt(): ?bool
    {
        return $this->worthIt;
    }

    public function rate(bool $worthIt): void
    {
        $this->worthIt = $worthIt;
    }
}
