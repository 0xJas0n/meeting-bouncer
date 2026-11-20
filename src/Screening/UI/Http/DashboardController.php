<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Application\Command\ImportInvites;
use App\Screening\Application\Command\QueueEvaluation;
use App\Screening\Application\Command\SetHourlyRate;
use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Application\Port\CalendarConnection;
use App\Screening\Application\Query\ListUpcomingInvites;
use App\Screening\Application\ReadModel\InviteSummary;
use App\Screening\Domain\Model\Persona;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    private const REVIEW_TIMEOUT_SECONDS = 180;

    public function __construct(private readonly QueryBus $queries, private readonly CommandBus $commands, private readonly PersonaSelection $personas, private readonly LoggerInterface $logger, private readonly CalendarConnection $calendar, private readonly GoogleSync $googleSync, private readonly HourlyRateStore $rate)
    {
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (null !== $error = $this->googleSync->ifDue()) {
            $this->addFlash('error', $error);
        }
        /** @var list<InviteSummary> $invites */
        $invites = $this->queries->ask(new ListUpcomingInvites(7));
        $persona = $this->personas->current($request);
        // Session = single demo user, like the persona. Queued ids that are still pending are "being reviewed now".
        $session = $request->getSession();
        // A worker that never answers (stopped, or a re-review by another channel) must not leave the spinner on forever.
        if (time() - (int) $session->get('reviewingAt', 0) > self::REVIEW_TIMEOUT_SECONDS) {
            $session->remove('reviewing');
        }
        $reviewing = array_values(array_intersect((array) $session->get('reviewing', []), array_map(static fn (InviteSummary $i): string => $i->id, array_filter($invites, static fn (InviteSummary $i): bool => 'pending' === $i->status || self::isStale($i, $persona)))));
        $session->set('reviewing', $reviewing);

        return $this->render('dashboard/index.html.twig', [
            'reviewing' => $reviewing,
            'invites' => $invites,
            'pendingCount' => \count(array_filter($invites, static fn (InviteSummary $i): bool => 'pending' === $i->status)),
            'staleCount' => \count(array_filter($invites, static fn (InviteSummary $i): bool => self::isStale($i, $persona))),
            'persona' => $persona,
            'personas' => $this->personas->available($request),
            'googleConfigured' => $this->calendar->isConfigured(),
            'googleConnected' => $this->calendar->isConnected(),
            'hourlyRate' => $this->rate->cents() / 100,
        ]);
    }

    #[Route('/persona', name: 'persona_switch', methods: ['POST'])]
    public function switchPersona(Request $request): RedirectResponse
    {
        $before = $this->personas->current($request);
        $this->personas->switch($request, (string) $request->request->get('persona'));
        if ($this->personas->current($request) !== $before) {
            $this->addFlash('persona_switched', '1'); // one-shot: the dashboard offers a re-review once
        }

        return $this->redirect((string) $request->headers->get('referer', $this->generateUrl('dashboard')));
    }

    #[Route('/hourly-rate', name: 'hourly_rate', methods: ['POST'])]
    public function setHourlyRate(Request $request): RedirectResponse
    {
        $this->denyUnlessCsrf($request, 'hourly_rate');
        try {
            $this->commands->dispatch(new SetHourlyRate((float) str_replace(',', '.', (string) $request->request->get('eur'))));
            $this->addFlash('success', 'flash.rate_saved');
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'flash.rate_invalid');
        }

        return $this->redirectToRoute('dashboard');
    }

    /**
     * Queues evaluation of everything still pending (one async message per invite; run `messenger:consume async`). scope=open also re-reviews undecided invites
     * a different persona reviewed (opt-in only).
     */
    #[Route('/evaluate-all', name: 'evaluate_all', methods: ['POST'])]
    public function evaluateAll(Request $request): RedirectResponse
    {
        $this->denyUnlessCsrf($request, 'evaluate_all');
        /** @var list<InviteSummary> $invites */
        $invites = $this->queries->ask(new ListUpcomingInvites(7));
        $persona = $this->personas->current($request);
        $withStale = 'open' === $request->request->get('scope');
        $queued = [];
        try {
            foreach ($invites as $invite) {
                if ('pending' === $invite->status || ($withStale && self::isStale($invite, $persona))) {
                    $this->commands->dispatch(new QueueEvaluation($invite->id, $persona));
                    $queued[] = $invite->id;
                }
            }
        } catch (\Throwable $e) {
            // e.g. LLM unreachable or bad key. Log the type only, never content (GDPR).
            $this->logger->error('Evaluate all failed', ['exception' => $e::class]);
            $this->addFlash('error', 'flash.evaluate_failed');
        }

        $request->getSession()->set('reviewing', $queued);
        $request->getSession()->set('reviewingAt', time());

        return $this->redirectToRoute('dashboard');
    }

    /** Deletes every invite, decision and saving (imported ones too), then loads the invented demo week. */
    #[Route('/demo/import', name: 'demo_import', methods: ['POST'])]
    public function importDemo(Request $request): RedirectResponse
    {
        $this->denyUnlessCsrf($request, 'demo_import');
        $this->commands->dispatch(new ImportInvites('fixtures', replace: true));
        $this->addFlash('success', 'flash.demo_loaded');

        return $this->redirectToRoute('dashboard');
    }

    /** Reviewed, not decided yet, and by someone other than today's reviewer. */
    private static function isStale(InviteSummary $invite, Persona $current): bool
    {
        return 'evaluated' === $invite->status && $invite->reviewedBy !== $current->value;
    }

    private function denyUnlessCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }
}
