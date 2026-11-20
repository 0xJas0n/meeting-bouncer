<?php

declare(strict_types=1);

namespace App\Screening\UI\Console;

use App\Screening\UI\Mcp\BouncerTools;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/** Same data as the MCP tool, as JSON on stdout. Exit code 1 when the draft is not ready to send. */
#[AsCommand(name: 'app:precheck', description: 'Check a draft invite before sending and print flags and suggestions as JSON')]
final class PrecheckCommand
{
    public function __construct(private readonly BouncerTools $tools)
    {
    }

    public function __invoke(
        OutputInterface $out,
        #[Argument] string $title,
        #[Argument] int $attendees,
        #[Argument] int $minutes,
        #[Argument(description: 'Invite description / agenda')] string $description = '',
    ): int {
        $r = $this->tools->precheckDraft($title, $description, $attendees, $minutes);
        $out->writeln(json_encode($r, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return $r['ready'] ? Command::SUCCESS : Command::FAILURE;
    }
}
