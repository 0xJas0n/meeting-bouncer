<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

/** message is the English text (LLM prompt, MCP); the UI translates "flag.<code>" with params. */
final readonly class Flag
{
    /** @param array<string, int> $params */
    public function __construct(public string $code, public string $message, public array $params = [])
    {
    }
}
