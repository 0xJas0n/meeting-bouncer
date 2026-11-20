<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

use App\Shared\Domain\Money;

/** Result of the rules-only door check: all signals plus human-readable flags. */
final readonly class DoorCheck
{
    /** @param list<Signal> $signals */
    public function __construct(private array $signals)
    {
    }

    /** @return array<string, bool|int|string|null> */
    public function signals(): array
    {
        $out = [];
        foreach ($this->signals as $s) {
            $out[$s->name] = $s->value;
        }

        return $out;
    }

    /** @return list<Flag> */
    public function flags(): array
    {
        return array_values(array_filter(array_map(static fn (Signal $s): ?Flag => $s->flag, $this->signals)));
    }

    public function hasFlag(string $code): bool
    {
        foreach ($this->flags() as $f) {
            if ($f->code === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{name: string, value: bool|int|string|null, flag: array{code: string, message: string, params?: array<string, int>}|null}> */
    public function toArray(): array
    {
        return array_map(static fn (Signal $s): array => [
            'name' => $s->name,
            'value' => $s->value,
            'flag' => null === $s->flag ? null : ['code' => $s->flag->code, 'message' => $s->flag->message, 'params' => $s->flag->params],
        ], $this->signals);
    }

    /** @param list<array{name: string, value: bool|int|string|null, flag: array{code: string, message: string, params?: array<string, int>}|null}> $rows */
    public static function fromArray(array $rows): self
    {
        return new self(array_map(static fn (array $r): Signal => new Signal(
            $r['name'],
            $r['value'],
            null === $r['flag'] ? null : new Flag($r['flag']['code'], $r['flag']['message'], $r['flag']['params'] ?? []),
        ), $rows));
    }

    public function cost(): Money
    {
        return Money::ofCents((int) ($this->signals()['costCents'] ?? 0));
    }
}
