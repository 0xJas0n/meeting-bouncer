<?php

declare(strict_types=1);

namespace App\Screening\UI\Console;

use App\Screening\Application\Command\EvaluateInvite;
use App\Screening\Application\Query\ListUpcomingInvites;
use App\Screening\Application\ReadModel\InviteSummary;
use App\Screening\Domain\Model\Persona;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:evaluate-all', description: 'Evaluate all upcoming invites (calls the LLM)')]
final class EvaluateAllCommand
{
    public function __construct(private readonly QueryBus $queries, private readonly CommandBus $commands)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'DOORMAN, NEW_YORKER, SURFER, PIRATE, SHERIFF, BERLIN, WIENER_GRANT, SCHWABE, KOELNER or BAYER')] string $persona = 'DOORMAN'): int
    {
        $persona = Persona::fromInput($persona);
        /** @var list<InviteSummary> $invites */
        $invites = $this->queries->ask(new ListUpcomingInvites(7));
        foreach ($invites as $invite) {
            $this->commands->dispatch(new EvaluateInvite($invite->id, $persona));
            $io->writeln('evaluated '.$invite->id);
        }

        return Command::SUCCESS;
    }
}
