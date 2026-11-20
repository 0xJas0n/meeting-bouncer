<?php

declare(strict_types=1);

namespace App\Screening\Domain\Model;

enum Persona: string
{
    case DOORMAN = 'DOORMAN';
    case NEW_YORKER = 'NEW_YORKER';
    case SURFER = 'SURFER';
    case PIRATE = 'PIRATE';
    case SHERIFF = 'SHERIFF';
    case BERLIN = 'BERLIN';
    case WIENER_GRANT = 'WIENER_GRANT';
    case SCHWABE = 'SCHWABE';
    case KOELNER = 'KOELNER';
    case BAYER = 'BAYER';

    /** The language the persona speaks. Only offered in the UI of that language. */
    public function language(): Language
    {
        return match ($this) {
            self::DOORMAN, self::NEW_YORKER, self::SURFER, self::PIRATE, self::SHERIFF => Language::EN,
            self::BERLIN, self::WIENER_GRANT, self::SCHWABE, self::KOELNER, self::BAYER => Language::DE,
        };
    }

    /** @return list<self> */
    public static function forLanguage(Language $language): array
    {
        return array_values(array_filter(self::cases(), static fn (self $p): bool => $p->language() === $language));
    }

    public static function defaultFor(Language $language): self
    {
        return self::forLanguage($language)[0];
    }

    public static function fromInput(string $value): self
    {
        return self::tryFrom(strtoupper(trim($value))) ?? throw new \InvalidArgumentException(sprintf('Unknown persona "%s". Use one of: %s.', $value, implode(', ', array_column(self::cases(), 'value'))));
    }
}
