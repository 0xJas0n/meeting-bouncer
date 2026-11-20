<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Application\Port\CalendarConnection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Read-only Google Calendar link: connect (imports right away), update now, disconnect. Nothing is ever written to the calendar. */
#[Route('/calendar/google')]
final class GoogleCalendarController extends AbstractController
{
    private const STATE_KEY = 'google_oauth_state';

    public function __construct(private readonly CalendarConnection $connection, private readonly GoogleSync $sync, private readonly LoggerInterface $logger)
    {
    }

    #[Route('/connect', name: 'google_connect', methods: ['GET'])]
    public function connect(Request $request): RedirectResponse
    {
        if (!$this->connection->isConfigured()) {
            $this->addFlash('error', 'flash.google_unconfigured');

            return $this->redirectToRoute('dashboard');
        }
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set(self::STATE_KEY, $state);

        return $this->redirect($this->connection->authorizationUrl($this->redirectUri(), $state));
    }

    #[Route('/callback', name: 'google_callback', methods: ['GET'])]
    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->getSession()->remove(self::STATE_KEY);
        if ('' === $expected || !hash_equals($expected, (string) $request->query->get('state'))) {
            throw new AccessDeniedHttpException('Invalid OAuth state.');
        }
        if ($request->query->has('error') || !$request->query->has('code')) {
            $this->addFlash('error', 'flash.google_denied');

            return $this->redirectToRoute('dashboard');
        }

        try {
            $this->connection->complete((string) $request->query->get('code'), $this->redirectUri());
        } catch (\Throwable $e) {
            $this->logger->error('Google connect failed', ['exception' => $e::class]);
            $this->addFlash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'flash.google_connect_failed');

            return $this->redirectToRoute('dashboard');
        }
        $error = $this->sync->now();
        $this->addFlash(null === $error ? 'success' : 'error', $error ?? 'flash.google_connected');

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/import', name: 'google_import', methods: ['POST'])]
    public function import(Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'google_import');
        $error = $this->sync->now();
        $this->addFlash(null === $error ? 'success' : 'error', $error ?? 'flash.google_imported');

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/disconnect', name: 'google_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): RedirectResponse
    {
        $this->checkCsrf($request, 'google_disconnect');
        $this->connection->disconnect();
        $this->sync->forget();
        $this->addFlash('success', 'flash.google_disconnected');

        return $this->redirectToRoute('dashboard');
    }

    private function redirectUri(): string
    {
        return $this->generateUrl('google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function checkCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }
}
