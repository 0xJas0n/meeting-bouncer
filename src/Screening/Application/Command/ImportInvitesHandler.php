<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

use App\Screening\Domain\Event\AllInvitesRemoved;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Screening\Application\Import\InviteFactory;
use App\Screening\Application\Import\CalendarSources;
use App\Screening\Domain\Model\FocusBlock;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\TimeSlot;
use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Port\FocusBlockRepository;
use App\Screening\Domain\Port\InviteRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ImportInvitesHandler
{
    public function __construct(
        private CalendarSources $sources,
        private InviteFactory $factory,
        private InviteRepository $invites,
        private FocusBlockRepository $focusBlocks,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ImportInvites $command): void
    {
        $source = $this->sources->get($command->source);
        if ($command->replace || $this->replacesDemoData($command->source)) {
            $this->invites->removeAll();
            $this->focusBlocks->removeAll();
            $this->events->publish(new AllInvitesRemoved($this->clock->now()));
        }
        foreach ($source->invites() as $raw) {
            if (null === $this->invites->find(new InviteId($raw->id))) {
                $this->invites->save($this->factory->create($raw));
            }
        }
        foreach ($source->focusBlocks() as $raw) {
            if (!$this->focusBlocks->has($raw->id)) {
                $this->focusBlocks->save(new FocusBlock($raw->id, TimeSlot::startingAt($raw->start, new Duration($raw->durationMinutes))));
            }
        }
    }

    /**
     * Real calendar data never mixes with the demo week: the first real import wipes it.
     * Only while demo invites are still there, so later re-imports keep confirmed verdicts and ratings.
     */
    private function replacesDemoData(string $source): bool
    {
        if ('fixtures' === $source) {
            return false;
        }
        foreach ($this->sources->get('fixtures')->invites() as $raw) {
            if (null !== $this->invites->find(new InviteId($raw->id))) {
                return true;
            }
        }

        return false;
    }
}
