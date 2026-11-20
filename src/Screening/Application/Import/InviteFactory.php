<?php

declare(strict_types=1);

namespace App\Screening\Application\Import;

use App\Screening\Domain\Model\AttendeeCount;
use App\Screening\Domain\Model\Duration;
use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;
use App\Screening\Domain\Model\Recurrence;
use App\Screening\Domain\Model\TimeSlot;
use App\Screening\Domain\Model\Title;
use App\Screening\Domain\Privacy\Pseudonymizer;

/**
 * Anti-corruption layer: RawInvite (personal data) in, pseudonymized Invite out.
 * After this point no name, email or phone number exists in the system, except the organizer's email (reply link only).
 */
final readonly class InviteFactory
{
    public function __construct(private Pseudonymizer $pseudonymizer)
    {
    }

    public function create(RawInvite $raw): Invite
    {
        // The user themself is not pseudonymized (no label), everybody else gets "Person A..".
        $others = array_values(array_filter($raw->attendees, static fn (RawAttendee $a): bool => !$a->isSelf));
        $aliases = array_map(static fn (RawAttendee $a): array => [$a->name, $a->email], $others);
        $labels = array_map(fn (int $i) => $this->pseudonymizer->labelFor($i), array_keys($aliases));

        $title = Title::fromRedacted($this->pseudonymizer->redact($raw->title, $aliases));
        $description = $this->pseudonymizer->redact($raw->description, $aliases);
        $self = array_values(array_filter($raw->attendees, static fn (RawAttendee $a): bool => $a->isSelf))[0] ?? null;

        return new Invite(
            new InviteId($raw->id),
            $title,
            $description,
            TimeSlot::startingAt($raw->start, new Duration($raw->durationMinutes)),
            new AttendeeCount(max(1, \count($raw->attendees))),
            $labels,
            null !== $self && $self->optional,
            null === $raw->recurringSince ? null : new Recurrence($raw->recurringSince),
            $raw->language,
            $raw->organizerEmail,
        );
    }
}
