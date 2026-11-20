<?php

declare(strict_types=1);

namespace App\Screening\Application\ReadModel;

final readonly class PrecheckResult
{
    /**
     * @param list<string> $flags
     * @param list<string> $suggestions
     */
    public function __construct(
        public int $costCents,
        public array $flags,
        public array $suggestions,
        public bool $ready,
        public ?string $aiReason = null,
    ) {
    }
}
