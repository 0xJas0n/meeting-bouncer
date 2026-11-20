<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

enum InviteStatus: string
{
    case PENDING = 'pending';
    case EVALUATED = 'evaluated';
    case CONFIRMED = 'confirmed';
    case OVERRIDDEN = 'overridden';
}
