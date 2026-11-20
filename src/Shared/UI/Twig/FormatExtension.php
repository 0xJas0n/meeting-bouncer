<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use Twig\Attribute\AsTwigFilter;

/** Display formats: 660,00 € and 1,5 h. */
final class FormatExtension
{
    #[AsTwigFilter('eur')]
    public function eur(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    #[AsTwigFilter('hours')]
    public function hours(float|int $hours): string
    {
        return rtrim(rtrim(number_format((float) $hours, 1, ',', '.'), '0'), ',').' h';
    }
}
