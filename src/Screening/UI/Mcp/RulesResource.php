<?php

declare(strict_types=1);

namespace App\Screening\UI\Mcp;

use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Domain\DoorCheck\RuleThresholds;
use Mcp\Capability\Attribute\McpResource;

final readonly class RulesResource
{
    public function __construct(private RuleThresholds $t, private HourlyRateStore $rate)
    {
    }

    #[McpResource(uri: 'bouncer://rules', name: 'bouncer-rules', description: 'Current door check thresholds of the Meeting Bouncer.', mimeType: 'text/plain')]
    public function rules(): string
    {
        return implode("\n", [
            'Meeting Bouncer dress code:',
            sprintf('- Agenda: at least %d characters and not just "sync", "catch-up", "quick call", ...', $this->t->minAgendaChars),
            sprintf('- Attendees: warn above %d', $this->t->warnAttendees),
            sprintf('- Optional in a meeting with more than %d attendees: strong "Bounce" hint', $this->t->optionalMinAttendees),
            sprintf('- Cost: attendees x hours x %s € per hour', number_format($this->rate->cents() / 100, 2, ',', '.')),
            sprintf('- Duration: %d minutes or longer without agenda is flagged', $this->t->longMeetingMinutes),
            '- Focus block collision: invite overlaps protected focus time',
            sprintf('- Back to back: third consecutive meeting without a %d minute break', $this->t->breakMinutes),
            sprintf('- Zombie: recurring series older than %d weeks', $this->t->zombieWeeks),
        ]);
    }
}
