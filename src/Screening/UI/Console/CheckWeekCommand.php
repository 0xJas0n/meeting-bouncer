<?php

declare(strict_types=1);

namespace App\Screening\UI\Console;

use App\Screening\UI\Mcp\BouncerTools;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/** Same data as the MCP tool: JSON on stdout for scripts and CI, or --pretty for humans (look of the promo video's MCP scene). */
#[AsCommand(name: 'app:check-week', description: 'Check the week (calls the LLM) and print verdicts and totals as JSON, or --pretty')]
final class CheckWeekCommand
{
    /** Colours from the promo video terminal. */
    private const COLOURS = ['LET_IN' => '#7bd08b', 'SHORTEN' => '#f0a35a', 'GO_ASYNC' => '#7fb7ee', 'BOUNCE' => '#f07a72'];

    public function __construct(private readonly BouncerTools $tools)
    {
    }

    public function __invoke(
        OutputInterface $out,
        #[Argument(description: 'Persona, e.g. DOORMAN or BERLIN')] string $persona = 'DOORMAN',
        #[Option(description: 'Coloured table for the terminal instead of JSON')] bool $pretty = false,
    ): int {
        $week = $this->tools->checkWeek($persona);

        if (!$pretty) {
            $out->writeln(json_encode($week, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $out->writeln('<fg=#8f836c>● meeting-bouncer · check_week</>');
        $out->writeln('');
        foreach ($week['verdicts'] as $v) {
            $label = $v['verdictLabel'] ?? 'Pending';
            if (null !== $v['suggestedDurationMin']) {
                $label .= ' → '.$v['suggestedDurationMin'].' min';
            }
            $out->writeln(sprintf(
                '  <fg=#8f836c>%s</>  %s  <fg=#a89b82>%s</>  <fg=%s;options=bold>%s</>',
                $v['start'],
                OutputFormatter::escape(self::pad($v['title'], 32)),
                self::pad(self::number($v['costEur']).' €', 8, \STR_PAD_LEFT),
                self::COLOURS[$v['verdict']] ?? 'default',
                $label,
            ));
            foreach ($v['flags'] as $flag) {
                $out->writeln('  <fg=#8f836c>'.str_repeat(' ', 18).'⚠ '.OutputFormatter::escape($flag).'</>');
            }
        }

        $t = $week['totals'];
        $out->writeln('  <fg=#5a4d38>'.str_repeat('╌', 72).'</>');
        $out->writeln(sprintf(
            '  %d invites · potentially <fg=#7bd08b;options=bold>%s h</> and <fg=#7bd08b;options=bold>%s €</> saved',
            $t['invites'],
            self::number($t['potentialHoursSaved']),
            self::number($t['potentialEurSaved']),
        ));

        return Command::SUCCESS;
    }

    /** Pads or cuts to a fixed display width (multibyte safe). */
    private static function pad(string $s, int $width, int $type = \STR_PAD_RIGHT): string
    {
        $s = mb_strwidth($s) > $width ? mb_strimwidth($s, 0, $width, '…') : $s;

        return mb_str_pad($s, $width, ' ', $type);
    }

    /** German number format, decimals only when needed: 1.320 / 2,5. */
    private static function number(int|float $n): string
    {
        return number_format($n, fmod((float) $n, 1.0) > 0 ? 1 : 0, ',', '.');
    }
}
