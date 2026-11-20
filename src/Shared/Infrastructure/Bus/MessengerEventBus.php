<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\EventBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBus
{
    public function __construct(#[Autowire(service: 'event.bus')] private MessageBusInterface $bus)
    {
    }

    public function publish(object $event): void
    {
        $this->bus->dispatch($event);
    }
}
