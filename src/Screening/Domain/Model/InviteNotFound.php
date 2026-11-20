<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

final class InviteNotFound extends \DomainException
{
    public static function withId(InviteId $id): self
    {
        return new self(sprintf('Invite "%s" not found.', $id->value));
    }
}
