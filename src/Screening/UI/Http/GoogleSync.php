<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Application\Command\ImportInvites;
use App\Screening\Application\Port\CalendarConnection;
use App\Shared\Application\Bus\CommandBus;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Pulls the next 7 days from Google: right after connecting, on "Update now", and at most once an hour on dashboard load.
 * ponytail: hourly = lazy on page view, not a background job. Add symfony/scheduler (or cron on bouncer:import-calendar) if data must refresh while nobody looks.
 */
final readonly class GoogleSync
{
    private const KEY = 'google_last_sync';
    private const TTL_SECONDS = 3600;

    public function __construct(private CalendarConnection $connection, private CommandBus $commands, private CacheInterface $cache, private LoggerInterface $logger)
    {
    }

    /** Imports now. Returns null on success, else a translation key or message for the flash. */
    public function now(): ?string
    {
        $this->cache->delete(self::KEY);

        return $this->ifDue();
    }

    public function ifDue(): ?string
    {
        if (!$this->connection->isConnected()) {
            return null;
        }
        $error = null;
        // Failures are cached too: a broken token must not hit Google on every page load. "Update now" retries.
        $this->cache->get(self::KEY, function (ItemInterface $item) use (&$error): int {
            $item->expiresAfter(self::TTL_SECONDS);
            try {
                $this->commands->dispatch(new ImportInvites('google'));
            } catch (\Throwable $e) {
                // never log the message: it could carry request details
                $this->logger->error('Google import failed', ['exception' => $e::class]);
                $error = $e instanceof \RuntimeException ? $e->getMessage() : 'flash.google_import_failed';
            }

            return time();
        });

        return $error;
    }

    public function forget(): void
    {
        $this->cache->delete(self::KEY);
    }
}
