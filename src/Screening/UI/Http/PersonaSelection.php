<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Domain\Model\Language;
use App\Screening\Domain\Model\Persona;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The chosen bouncer persona lives in the session (single demo user).
 * Personas speak one language and are only available in the UI of that language.
 */
final class PersonaSelection
{
    private const KEY = 'persona';

    public function current(Request $request): Persona
    {
        $language = $this->language($request);
        $persona = Persona::tryFrom((string) $request->getSession()->get(self::KEY));

        return $persona?->language() === $language ? $persona : Persona::defaultFor($language);
    }

    /** @return list<Persona> */
    public function available(Request $request): array
    {
        return Persona::forLanguage($this->language($request));
    }

    public function switch(Request $request, string $value): void
    {
        $persona = Persona::fromInput($value);
        $persona->language() === $this->language($request) || throw new BadRequestHttpException('Persona not available in this language.');
        $request->getSession()->set(self::KEY, $persona->value);
    }

    private function language(Request $request): Language
    {
        return Language::tryFrom($request->getLocale()) ?? Language::EN;
    }
}
