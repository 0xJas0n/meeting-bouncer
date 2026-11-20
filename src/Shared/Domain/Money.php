<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/** Euro amount in integer cents, so no float rounding in cost maths. */
final readonly class Money
{
    private function __construct(public int $cents)
    {
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function euros(): float
    {
        return $this->cents / 100;
    }
}
