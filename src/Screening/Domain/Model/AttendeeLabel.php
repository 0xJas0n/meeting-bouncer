<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

/** Pseudonym such as "Person A". Stable per invite. */
final readonly class AttendeeLabel implements \Stringable
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
