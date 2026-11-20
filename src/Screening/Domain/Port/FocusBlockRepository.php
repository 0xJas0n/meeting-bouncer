<?php

declare(strict_types=1);

namespace App\Screening\Domain\Port;

use App\Screening\Domain\Model\FocusBlock;

interface FocusBlockRepository
{
    public function save(FocusBlock $block): void;

    public function has(string $id): bool;

    public function removeAll(): void;

    /** @return list<FocusBlock> blocks overlapping the range */
    public function overlapping(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
