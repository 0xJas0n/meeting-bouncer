<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

/**
 * Free text that already went through the Pseudonymizer. Only the Pseudonymizer
 * should create it, so the type system documents "no personal data in here".
 */
final readonly class RedactedText implements \Stringable
{
    public function __construct(public string $value)
    {
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
