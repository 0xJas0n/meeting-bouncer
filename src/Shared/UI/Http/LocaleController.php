<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Screening\Domain\Model\Language;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** The UI language lives in the session (single demo user), like the persona. Default: framework default_locale. */
final class LocaleController extends AbstractController
{
    private const KEY = 'locale';

    #[AsEventListener(priority: 20)] // after the router (32), before the translator's locale listener (10)
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasPreviousSession() && $locale = Language::tryFrom((string) $request->getSession()->get(self::KEY))) {
            $request->setLocale($locale->value);
        }
    }

    #[Route('/locale', name: 'locale_switch', methods: ['POST'])]
    public function switch(Request $request): RedirectResponse
    {
        $language = Language::tryFrom((string) $request->request->get('locale')) ?? throw new BadRequestHttpException('Unknown language.');
        $request->getSession()->set(self::KEY, $language->value);

        return $this->redirect((string) $request->headers->get('referer', $this->generateUrl('dashboard')));
    }
}
