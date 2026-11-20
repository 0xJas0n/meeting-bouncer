<?php

declare(strict_types=1);

namespace App\Screening\UI\Console;

use App\Screening\Application\Command\ImportInvites;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-fixtures', description: 'Import demo invites from fixtures/invites.json')]
final class ImportFixturesCommand
{
    public function __construct(private readonly CommandBus $bus)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->bus->dispatch(new ImportInvites());
        $io->success('Fixtures imported.');

        return Command::SUCCESS;
    }
}
