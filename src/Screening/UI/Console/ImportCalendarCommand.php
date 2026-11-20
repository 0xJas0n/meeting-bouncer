<?php

declare(strict_types=1);

namespace App\Screening\UI\Console;

use App\Screening\Application\Command\ImportInvites;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-calendar', description: 'Import invites from a calendar source (fixtures or google)')]
final class ImportCalendarCommand
{
    public function __construct(private readonly CommandBus $bus)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: '"fixtures" or "google" (connect Google in the web UI first)')] string $source = 'fixtures'): int
    {
        try {
            $this->bus->dispatch(new ImportInvites($source));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('Imported from "%s".', $source));

        return Command::SUCCESS;
    }
}
