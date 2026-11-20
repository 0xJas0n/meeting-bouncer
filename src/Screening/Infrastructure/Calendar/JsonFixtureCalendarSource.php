<?php

declare(strict_types=1);

namespace App\Screening\Infrastructure\Calendar;

use App\Screening\Application\Import\RawAttendee;
use App\Screening\Application\Import\RawFocusBlock;
use App\Screening\Application\Import\RawInvite;
use App\Screening\Application\Port\CalendarSource;
use App\Screening\Domain\Model\Language;
use App\Shared\Domain\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads fixtures/invites.json (invented data only). Dates are relative: `dayOffset` counts
 * working days from today, so the demo always shows "this week".
 */
final readonly class JsonFixtureCalendarSource implements CalendarSource
{
    public function __construct(
        private Clock $clock,
        #[Autowire('%kernel.project_dir%/fixtures/invites.json')]
        private string $path,
    ) {
    }

    public function name(): string
    {
        return 'fixtures';
    }

    public function invites(): iterable
    {
        foreach ($this->load()['invites'] as $row) {
            yield new RawInvite(
                $row['id'],
                $row['title'],
                $row['description'] ?? '',
                $this->at($row['dayOffset'], $row['time']),
                $row['durationMin'],
                array_values(array_map(
                    static fn (array $a): RawAttendee => new RawAttendee($a['name'], $a['email'], $a['self'] ?? false, $a['optional'] ?? false),
                    $row['attendees'],
                )),
                isset($row['recurringWeeks']) ? $this->clock->now()->modify(sprintf('-%d weeks', $row['recurringWeeks'])) : null,
                Language::from($row['language'] ?? 'en'),
                $row['organizerEmail'] ?? null,
            );
        }
    }

    public function focusBlocks(): iterable
    {
        foreach ($this->load()['focusBlocks'] ?? [] as $row) {
            yield new RawFocusBlock($row['id'], $this->at($row['dayOffset'], $row['time']), $row['durationMin']);
        }
    }

    private function at(int $workingDays, string $time): \DateTimeImmutable
    {
        $day = $this->clock->now()->setTime(0, 0);
        while ($day->format('N') >= 6) {
            $day = $day->modify('+1 day');
        }
        for ($i = 0; $i < $workingDays; ++$i) {
            do {
                $day = $day->modify('+1 day');
            } while ($day->format('N') >= 6);
        }
        [$h, $m] = array_map('intval', explode(':', $time));

        return $day->setTime($h, $m);
    }

    /** @return array{invites: list<array<string, mixed>>, focusBlocks?: list<array<string, mixed>>} */
    private function load(): array
    {
        /** @var array{invites: list<array<string, mixed>>, focusBlocks?: list<array<string, mixed>>} */
        return json_decode((string) file_get_contents($this->path), true, 512, \JSON_THROW_ON_ERROR);
    }
}
