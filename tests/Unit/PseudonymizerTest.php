<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Screening\Domain\Privacy\Pseudonymizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PseudonymizerTest extends TestCase
{
    /** @return iterable<string, array{string, list<list<string>>, string}> */
    public static function cases(): iterable
    {
        $erika = ['Erika Mustermann', 'erika.mustermann@example.com'];
        yield 'attendee name' => ['Ask Erika Mustermann', [$erika], 'Ask Person A'];
        yield 'first name only' => ['Erika will present', [$erika], 'Person A will present'];
        yield 'email' => ['Mail erika.mustermann@example.com', [$erika], 'Mail Person A'];
        yield 'unknown email' => ['Mail someone@corp.example.org', [], 'Mail [email]'];
        yield 'obfuscated email' => ['Mail max [at] example.com', [], 'Mail [email]'];
        yield 'AT phone' => ['Call +43 660 1234567 now', [], 'Call [phone] now'];
        yield 'DE phone' => ['Tel 030 / 1234 5678', [], 'Tel [phone]'];
        yield 'personal url' => ['See https://www.linkedin.com/in/erika-m please', [], 'See [link] please'];
        yield 'secret url' => ['Join https://zoom.example.com/j/123?pwd=abc', [], 'Join [link]'];
        yield 'harmless url kept' => ['Slides https://wiki.example.com/roadmap', [], 'Slides https://wiki.example.com/roadmap'];
        yield 'surname only' => ['Per Mustermann: numbers', [$erika], 'Per Person A: numbers'];
        yield 'case insensitive' => ['ERIKA MUSTERMANN joins', [$erika], 'Person A joins'];
        yield 'umlaut name' => ['Frage an Jürgen Müller', [['Jürgen Müller', 'j.mueller@example.com']], 'Frage an Person A'];
        yield 'two attendees get two labels' => ['Erika and Max agree', [$erika, ['Max Beispiel', 'max.beispiel@example.com']], 'Person A and Person B agree'];
        yield 'name inside longer word kept' => ['Erikasberg office', [$erika], 'Erikasberg office'];
        yield 'AT phone 0043' => ['Call 0043 1 2345678', [], 'Call [phone]'];
        yield 'phone with brackets' => ['Call (01) 234 56 78', [], 'Call [phone]'];
        yield 'email in url query' => ['Open https://app.example.com/x?email=erika@example.com', [], 'Open [link]'];
        yield 'mailto' => ['Write mailto:erika@example.com', [], 'Write [link]'];
        yield 'iso date kept' => ['Deadline 2026-11-05 noon', [], 'Deadline 2026-11-05 noon'];
        yield 'german date kept' => ['Frist 05.11.2026 mittags', [], 'Frist 05.11.2026 mittags'];
        yield 'time range kept' => ['Slot 10:00-11:30 only', [], 'Slot 10:00-11:30 only'];
        yield 'dates and counts kept' => ['Q1 2026: 49 EUR vs 59 EUR', [], 'Q1 2026: 49 EUR vs 59 EUR'];
    }

    /** @param list<list<string>> $attendees */
    #[DataProvider('cases')]
    public function testRedaction(string $input, array $attendees, string $expected): void
    {
        self::assertSame($expected, (new Pseudonymizer())->redact($input, $attendees)->value);
    }

    public function testLabelsAreStableAndExtend(): void
    {
        $p = new Pseudonymizer();
        self::assertSame('Person A', $p->labelFor(0)->value);
        self::assertSame('Person Z', $p->labelFor(25)->value);
        self::assertSame('Person AA', $p->labelFor(26)->value);
    }
}
