<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

enum VerdictType: string
{
    case LET_IN = 'LET_IN';
    case SHORTEN = 'SHORTEN';
    case GO_ASYNC = 'GO_ASYNC';
    case BOUNCE = 'BOUNCE';
}
