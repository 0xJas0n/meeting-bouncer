<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

final readonly class DoorChecker
{
    /** @param iterable<DoorRule> $rules */
    public function __construct(private iterable $rules)
    {
    }

    public function check(DoorContext $context): DoorCheck
    {
        $signals = [];
        foreach ($this->rules as $rule) {
            $signals[] = $rule->evaluate($context);
        }

        return new DoorCheck($signals);
    }
}
