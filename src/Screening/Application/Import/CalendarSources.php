<?php

declare(strict_types=1);

namespace App\Screening\Application\Import;

use App\Screening\Application\Port\CalendarSource;

/** Picks a CalendarSource by name. New sources are registered by tagging, no edits here. */
final readonly class CalendarSources
{
    /** @param iterable<CalendarSource> $sources */
    public function __construct(private iterable $sources)
    {
    }

    public function get(string $name): CalendarSource
    {
        $names = [];
        foreach ($this->sources as $source) {
            if ($source->name() === $name) {
                return $source;
            }
            $names[] = $source->name();
        }

        throw new \InvalidArgumentException(sprintf('Unknown calendar source "%s". Available: %s.', $name, implode(', ', $names)));
    }
}
