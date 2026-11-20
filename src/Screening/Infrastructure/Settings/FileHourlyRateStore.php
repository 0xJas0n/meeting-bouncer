<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Settings;

use App\Screening\Application\Port\HourlyRateStore;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Single demo user: one number in var/ (shared by web, worker and stdio MCP server). Falls back to bouncer.hourly_rate_eur.
 * ponytail: one global rate, a per-user/team rate would need a DB column.
 */
final readonly class FileHourlyRateStore implements HourlyRateStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/hourly_rate_%kernel.environment%')] private string $path,
        #[Autowire('%bouncer.hourly_rate_eur%')] private int $defaultEur,
    ) {
    }

    public function cents(): int
    {
        $stored = is_file($this->path) ? (int) file_get_contents($this->path) : 0;

        return $stored > 0 ? $stored : $this->defaultEur * 100;
    }

    public function save(int $cents): void
    {
        $tmp = $this->path.'.tmp';
        file_put_contents($tmp, (string) $cents);
        rename($tmp, $this->path);
    }
}
