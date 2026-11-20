<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class InviteId implements \Stringable
{
    public function __construct(public string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('InviteId must not be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
