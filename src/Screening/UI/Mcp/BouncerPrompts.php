<?php

declare(strict_types=1);

namespace App\Screening\UI\Mcp;

use App\Screening\Domain\Model\Persona;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Exception\PromptGetException;

/** Ready-made workflows that show up as slash commands in MCP clients such as Claude Code. */
final readonly class BouncerPrompts
{
    /** @return array{user: string} */
    #[McpPrompt(name: 'weekly-review', description: 'Review the week: run check_week and name the meetings worth declining or shortening.')]
    public function weeklyReview(string $persona = 'DOORMAN'): array
    {
        try {
            $persona = Persona::fromInput($persona)->value;
        } catch (\InvalidArgumentException $e) {
            throw new PromptGetException($e->getMessage(), 0, $e);
        }

        return ['user' => sprintf(
            'Use the meeting-bouncer tool check_week with persona %s. Then list the three best candidates to decline, shorten or move to async, each with title, cost in EUR and the reply draft. Ask me before you call confirm_verdict or override_verdict.',
            $persona,
        )];
    }

    /** @return array{user: string} */
    #[McpPrompt(name: 'draft-check', description: 'Check a draft invite with precheck_draft before it is sent and rewrite it.')]
    public function draftCheck(string $title, string $description = '', int $attendeeCount = 8, int $durationMin = 60): array
    {
        return ['user' => sprintf(
            "Use the meeting-bouncer tool precheck_draft for this draft invite and apply its suggestions to rewrite title and description:\nTitle: %s\nDescription: %s\nAttendees: %d\nDuration: %d min",
            $title,
            $description,
            $attendeeCount,
            $durationMin,
        )];
    }
}
