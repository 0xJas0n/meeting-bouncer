<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

/** One computed fact about an invite, optionally raising a flag. */
final readonly class Signal
{
    public function __construct(
        public string $name,
        public bool|int|string|null $value,
        public ?Flag $flag = null,
    ) {
    }
}
