<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Reporting\Domain\SavingsPolicy;
use App\Screening\Domain\Model\VerdictType;
use PHPUnit\Framework\TestCase;

final class SavingsPolicyTest extends TestCase
{
    public function testGoAsyncSavesTheWholeMeeting(): void
    {
        $s = (new SavingsPolicy())->calculate(VerdictType::GO_ASYNC, 11, 60, null, 66000);

        self::assertSame(660, $s->personMinutes);
        self::assertSame(66000, $s->cents);
    }

    public function testShortenSavesTheCutPart(): void
    {
        $s = (new SavingsPolicy())->calculate(VerdictType::SHORTEN, 7, 90, 45, 63000);

        self::assertSame(315, $s->personMinutes);
        self::assertSame(31500, $s->cents);
    }

    public function testLetInSavesNothing(): void
    {
        $s = (new SavingsPolicy())->calculate(VerdictType::LET_IN, 4, 30, null, 12000);

        self::assertSame(0, $s->cents);
        self::assertSame(0, $s->personMinutes);
    }

    public function testShortenWithoutSuggestionSavesNothing(): void
    {
        self::assertSame(0, (new SavingsPolicy())->calculate(VerdictType::SHORTEN, 7, 90, null, 63000)->cents);
    }
}
