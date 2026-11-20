<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Application\Command\ConfirmVerdict;
use App\Screening\Application\Command\EvaluateInvite;
use App\Screening\Application\Command\OverrideVerdict;
use App\Screening\Application\Command\RateMeeting;
use App\Screening\Application\Query\GetInviteVerdict;
use App\Screening\Domain\Model\InviteNotFound;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Thin: maps HTTP to commands/queries. Nothing is ever sent to a real calendar. */
#[Route('/invites/{id}', requirements: ['id' => '[A-Za-z0-9_-]+'])]
final class VerdictController extends AbstractController
{
    public function __construct(private readonly QueryBus $queries, private readonly CommandBus $commands, private readonly PersonaSelection $personas, private readonly LoggerInterface $logger)
    {
    }

    #[Route('', name: 'verdict_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        try {
            $view = $this->queries->ask(new GetInviteVerdict($id));
        } catch (InviteNotFound) {
            throw new NotFoundHttpException();
        }

        return $this->render('verdict/show.html.twig', ['v' => $view]);
    }

    #[Route('/evaluate', name: 'verdict_evaluate', methods: ['POST'])]
    public function evaluate(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, $request, fn () => $this->commands->dispatch(new EvaluateInvite($id, $this->personas->current($request))));
    }

    #[Route('/confirm', name: 'verdict_confirm', methods: ['POST'])]
    public function confirm(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, $request, fn () => $this->commands->dispatch(new ConfirmVerdict($id)));
    }

    #[Route('/override', name: 'verdict_override', methods: ['POST'])]
    public function override(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, $request, fn () => $this->commands->dispatch(new OverrideVerdict($id)));
    }

    #[Route('/rate', name: 'verdict_rate', methods: ['POST'])]
    public function rate(string $id, Request $request): RedirectResponse
    {
        return $this->act($id, $request, fn () => $this->commands->dispatch(new RateMeeting($id, 'yes' === $request->request->get('worth'))));
    }

    private function act(string $id, Request $request, \Closure $command): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('invite_'.$id, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        try {
            $command();
        } catch (InviteNotFound) {
            throw new NotFoundHttpException();
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            // e.g. LLM unreachable or bad key. Log the type only, never content (GDPR).
            $this->logger->error('Action failed', ['invite' => $id, 'exception' => $e::class]);
            $this->addFlash('error', 'flash.action_failed');
        }

        return $this->redirectToRoute('verdict_show', ['id' => $id]);
    }
}
