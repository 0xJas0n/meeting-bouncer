<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

use App\Screening\Domain\DoorCheck\DoorCheck;
use App\Screening\Domain\Event\InviteEvaluated;
use App\Screening\Domain\Event\MeetingRated;
use App\Screening\Domain\Event\VerdictConfirmed;
use App\Screening\Domain\Event\VerdictOverridden;
use App\Shared\Domain\AggregateRoot;

/**
 * A calendar invite, already pseudonymized. Holds no names, emails or phone numbers.
 * Doctrine hydrates the primitive fields directly; the public API speaks value objects.
 */
class Invite extends AggregateRoot
{
    private string $id;
    private string $title;
    private string $description;
    private \DateTimeImmutable $startsAt;
    private \DateTimeImmutable $endsAt;
    private int $attendeeCount;
    /** @var list<string> */
    private array $attendeeLabels;
    private bool $userOptional;
    private ?\DateTimeImmutable $recurringSince;
    private Language $language;
    /** Only kept so the user can answer the organizer; never part of the AI prompt. */
    private ?string $organizerEmail;
    private InviteStatus $status = InviteStatus::PENDING;
    private ?bool $worthIt = null;
    /** @var array<string, mixed>|null */
    private ?array $verdict = null;
    /** @var list<array{name: string, value: bool|int|string|null, flag: array{code: string, message: string}|null}>|null */
    private ?array $doorCheck = null;

    /** @param list<AttendeeLabel> $attendeeLabels */
    public function __construct(
        InviteId $id,
        Title $title,
        RedactedText $description,
        TimeSlot $slot,
        AttendeeCount $attendeeCount,
        array $attendeeLabels,
        bool $userOptional,
        ?Recurrence $recurrence,
        Language $language,
        ?string $organizerEmail = null,
    ) {
        $this->id = $id->value;
        $this->title = $title->value;
        $this->description = $description->value;
        $this->startsAt = $slot->start;
        $this->endsAt = $slot->end;
        $this->attendeeCount = $attendeeCount->value;
        $this->attendeeLabels = array_map(static fn (AttendeeLabel $l): string => $l->value, $attendeeLabels);
        $this->userOptional = $userOptional;
        $this->recurringSince = $recurrence?->since;
        $this->language = $language;
        $this->organizerEmail = $organizerEmail;
    }

    public function id(): InviteId
    {
        return new InviteId($this->id);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): RedactedText
    {
        return new RedactedText($this->description);
    }

    public function slot(): TimeSlot
    {
        return new TimeSlot($this->startsAt, $this->endsAt);
    }

    public function duration(): Duration
    {
        return $this->slot()->duration();
    }

    public function attendeeCount(): AttendeeCount
    {
        return new AttendeeCount($this->attendeeCount);
    }

    /** @return list<AttendeeLabel> */
    public function attendeeLabels(): array
    {
        return array_map(static fn (string $l): AttendeeLabel => new AttendeeLabel($l), $this->attendeeLabels);
    }

    public function isUserOptional(): bool
    {
        return $this->userOptional;
    }

    public function recurrence(): ?Recurrence
    {
        return null === $this->recurringSince ? null : new Recurrence($this->recurringSince);
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function organizerEmail(): ?string
    {
        return $this->organizerEmail;
    }

    public function status(): InviteStatus
    {
        return $this->status;
    }

    public function verdict(): ?Verdict
    {
        return null === $this->verdict ? null : Verdict::fromArray($this->verdict);
    }

    public function doorCheck(): ?DoorCheck
    {
        return null === $this->doorCheck ? null : DoorCheck::fromArray($this->doorCheck);
    }

    /** (Re-)evaluate, e.g. with another persona. A confirmed or overridden invite is final. */
    public function evaluate(DoorCheck $check, Verdict $verdict, \DateTimeImmutable $at): void
    {
        if (InviteStatus::PENDING !== $this->status && InviteStatus::EVALUATED !== $this->status) {
            throw new \DomainException(sprintf('Invite "%s" is already %s and cannot be re-evaluated.', $this->id, $this->status->value));
        }
        $this->doorCheck = $check->toArray();
        $this->verdict = $verdict->toArray();
        $this->status = InviteStatus::EVALUATED;
        $this->record(new InviteEvaluated($this->id(), $verdict->type, $at));
    }

    public function worthIt(): ?bool
    {
        return $this->worthIt;
    }

    /** The user accepts the verdict. Nothing is sent anywhere; this only records the decision. */
    public function confirm(\DateTimeImmutable $at): void
    {
        $verdict = $this->requireEvaluated();
        $this->status = InviteStatus::CONFIRMED;
        $this->record(new VerdictConfirmed(
            $this->id(),
            $verdict->type,
            $this->attendeeCount,
            $this->duration()->minutes,
            $verdict->suggestedDurationMin,
            $this->doorCheck()?->cost()->cents ?? 0,
            $at,
        ));
    }

    /** The user goes to the meeting anyway. */
    public function override(\DateTimeImmutable $at): void
    {
        $this->requireEvaluated();
        $this->status = InviteStatus::OVERRIDDEN;
        $this->record(new VerdictOverridden($this->id(), $at));
    }

    /** "Was it worth it?" Only for meetings the user attends (let in, or overridden), once. */
    public function rate(bool $worthIt, \DateTimeImmutable $at): void
    {
        $attended = InviteStatus::OVERRIDDEN === $this->status
            || (InviteStatus::CONFIRMED === $this->status && VerdictType::LET_IN === $this->verdict()?->type);
        if (!$attended) {
            throw new \DomainException('Only meetings you attend can be rated.');
        }
        if (null !== $this->worthIt) {
            throw new \DomainException('This meeting is already rated.');
        }
        $this->worthIt = $worthIt;
        $this->record(new MeetingRated($this->id(), $worthIt, $at));
    }

    private function requireEvaluated(): Verdict
    {
        if (InviteStatus::EVALUATED !== $this->status || null === $verdict = $this->verdict()) {
            throw new \DomainException(sprintf('Invite "%s" has no open verdict to decide on.', $this->id));
        }

        return $verdict;
    }
}
