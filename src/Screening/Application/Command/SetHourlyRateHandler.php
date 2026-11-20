<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Application\Port\HourlyRateStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** New rate applies to every cost shown from now on. Confirmed savings keep the cost they were confirmed with. */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetHourlyRateHandler
{
    public const MAX_EUR = 1000;

    public function __construct(private HourlyRateStore $rate)
    {
    }

    public function __invoke(SetHourlyRate $command): void
    {
        if (!($command->eur >= 1 && $command->eur <= self::MAX_EUR)) {
            throw new \InvalidArgumentException(sprintf('Hourly rate must be between 1 and %d EUR.', self::MAX_EUR));
        }
        $this->rate->save((int) round($command->eur * 100));
    }
}
