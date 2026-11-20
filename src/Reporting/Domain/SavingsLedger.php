<?php

declare(strict_types=1);

namespace App\Reporting\Domain;

interface SavingsLedger
{
    public function record(SavingsEntry $entry): void;

    public function find(string $inviteId): ?SavingsEntry;

    public function save(SavingsEntry $entry): void;

    public function clear(): void;

    /** @return list<SavingsEntry> */
    public function decidedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
