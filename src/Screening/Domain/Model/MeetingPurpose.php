<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

enum MeetingPurpose: string
{
    case DECISION = 'decision';
    case BRAINSTORM = 'brainstorm';
    case STATUS_UPDATE = 'status_update';
    case INFO_DUMP = 'info_dump';
    case ANNOUNCEMENT = 'announcement';
    case SOCIAL = 'social';
    case UNCLEAR = 'unclear';
}
