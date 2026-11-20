<?php

declare(strict_types=1);

namespace App\Screening\Domain\DoorCheck;

use App\Screening\Domain\Model\RedactedText;

final class AgendaDetector
{
    public function hasAgenda(RedactedText $description, RuleThresholds $t): bool
    {
        $text = mb_strtolower(trim($description->value));

        return mb_strlen($text) >= $t->minAgendaChars && !\in_array($text, $t->genericAgendas, true);
    }
}
