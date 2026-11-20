<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(#[Autowire(service: 'query.bus')] private MessageBusInterface $bus)
    {
    }

    public function ask(object $query): mixed
    {
        try {
            return $this->bus->dispatch($query)->last(HandledStamp::class)?->getResult();
        } catch (HandlerFailedException $e) {
            // unwrap so callers see the domain exception, not Messenger's wrapper
            throw $e->getPrevious() ?? $e;
        }
    }
}
