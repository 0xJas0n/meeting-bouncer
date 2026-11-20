<?php

declare(strict_types=1);

namespace App\Screening\Application\Command;

/** Import everything the named CalendarSource offers. Existing invites are left untouched, unless $replace wipes everything first. A non-fixture import also wipes everything while demo invites are present. */
final readonly class ImportInvites
{
    public function __construct(public string $source = 'fixtures', public bool $replace = false)
    {
    }
}
