<?php

declare(strict_types=1);

namespace App\Screening\Domain\Port;

use App\Screening\Domain\Model\Invite;
use App\Screening\Domain\Model\InviteId;

interface InviteRepository
{
    public function save(Invite $invite): void;

    public function find(InviteId $id): ?Invite;

    public function removeAll(): void;

    /**
     * User feedback on other invites with exactly this title: the "worth it?" rating,
     * or true for an unrated override (the user went anyway).
     *
     * @return list<bool>
     */
    public function feedbackForTitle(string $title, InviteId $except): array;

    /** @return list<Invite> ordered by start */
    public function startingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
