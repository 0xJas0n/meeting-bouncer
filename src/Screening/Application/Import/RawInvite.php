<?php

declare(strict_types=1);

namespace App\Screening\Application\Import;

use App\Screening\Domain\Model\Language;

final readonly class RawInvite
{
    /** @param list<RawAttendee> $attendees */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public \DateTimeImmutable $start,
        public int $durationMinutes,
        public array $attendees,
        public ?\DateTimeImmutable $recurringSince,
        public Language $language,
        public ?string $organizerEmail = null,
    ) {
    }
}
