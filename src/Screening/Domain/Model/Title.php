<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final readonly class Title implements \Stringable
{
    public const MAX_LENGTH = 200;

    private function __construct(public string $value)
    {
    }

    public static function fromRedacted(RedactedText $text): self
    {
        $value = trim($text->value);
        if ('' === $value) {
            throw new \InvalidArgumentException('Title must not be empty.');
        }

        return new self(mb_substr($value, 0, self::MAX_LENGTH));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
