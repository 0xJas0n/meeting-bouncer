<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Application\Port\CalendarConnection;
use App\Screening\UI\Http\GoogleSync;
use App\Shared\Application\Bus\CommandBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class GoogleSyncTest extends TestCase
{
    public function testImportsOncePerHourUnlessForced(): void
    {
        $bus = new class implements CommandBus {
            public int $count = 0;

            public function dispatch(object $command): void
            {
                ++$this->count;
            }
        };
        $connection = $this->createStub(CalendarConnection::class);
        $connection->method('isConnected')->willReturn(true);
        $sync = new GoogleSync($connection, $bus, new ArrayAdapter(), new NullLogger());

        $sync->ifDue();
        $sync->ifDue();
        self::assertSame(1, $bus->count);

        self::assertNull($sync->now());
        self::assertSame(2, $bus->count);
    }
}
