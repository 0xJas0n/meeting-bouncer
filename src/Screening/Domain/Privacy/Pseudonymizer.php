<?php

declare(strict_types=1);

namespace App\Screening\Domain\Privacy;

use App\Screening\Domain\Model\AttendeeLabel;
use App\Screening\Domain\Model\RedactedText;

/**
 * Only way to produce RedactedText. Replaces known attendees by stable labels
 * ("Person A", "Person B", ... in list order), then strips emails, phone numbers
 * and personal or secret-bearing URLs.
 */
final class Pseudonymizer
{
    private const EMAIL = '/[A-Z0-9._%+\-]+(?:@|\s*\[at\]\s*|\s*\(at\)\s*)[A-Z0-9\-]+(?:(?:\.|\s*\[dot\]\s*)[A-Z0-9\-]+)*\.[A-Z]{2,}/iu';
    private const PHONE = '/(?<![\w])(?:\+|00|\()?\d[\d\s().\/\-]{6,}\d(?![\w])/u';
    private const DATE = '/^(?:\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.(?:\d{2}|\d{4}))$/';
    private const URL = '#\b(?:https?://|mailto:)[^\s<>"\')]+#iu';
    private const PERSONAL_URL = '#(?:@|[?&](?:e?mail|user(?:name|id)?|uid|name|token|pwd|password|key)=|/(?:in|u|people|profile|user|users|pub)/|linkedin\.com|xing\.com|mailto:)#iu';

    public function labelFor(int $index): AttendeeLabel
    {
        $letters = '';
        $n = $index;
        do {
            $letters = chr(65 + $n % 26).$letters;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return new AttendeeLabel('Person '.$letters);
    }

    /**
     * @param list<list<string>> $attendees aliases (names, emails) per attendee; list position decides the label
     */
    public function redact(string $text, array $attendees = []): RedactedText
    {
        $text = $this->replaceAttendees($text, $attendees);
        $text = (string) preg_replace_callback(self::URL, static fn (array $m): string => 1 === preg_match(self::PERSONAL_URL, $m[0]) ? '[link]' : $m[0], $text);
        $text = (string) preg_replace(self::EMAIL, '[email]', $text);
        $text = (string) preg_replace_callback(self::PHONE, static fn (array $m): string => 1 === preg_match(self::DATE, trim($m[0])) ? $m[0] : '[phone]', $text);

        return new RedactedText($text);
    }

    /** @param list<list<string>> $attendees */
    private function replaceAttendees(string $text, array $attendees): string
    {
        $needles = [];
        foreach ($attendees as $i => $aliases) {
            $label = $this->labelFor($i)->value;
            foreach ($aliases as $alias) {
                $alias = trim($alias);
                if ('' === $alias) {
                    continue;
                }
                $needles[$alias] = $label;
                if (str_contains($alias, '@')) {
                    $needles[strstr($alias, '@', true)] ??= $label;
                    continue;
                }
                foreach (preg_split('/[\s,;]+/u', $alias) ?: [] as $part) {
                    // single name parts: privacy beats fidelity, but skip 1-2 letter tokens
                    if (mb_strlen($part) >= 3) {
                        $needles[$part] ??= $label;
                    }
                }
            }
        }
        // longest first so "Erika Mustermann" wins over "Erika"
        uksort($needles, static fn (int|string $a, int|string $b): int => mb_strlen((string) $b) <=> mb_strlen((string) $a));
        foreach ($needles as $needle => $label) {
            $text = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote((string) $needle, '/').'(?![\p{L}\p{N}])/iu', $label, $text);
        }

        return $text;
    }
}
