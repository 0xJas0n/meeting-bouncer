<?php

declare(strict_types=1);

namespace App\Screening\UI\Http;

use App\Screening\Application\Port\HourlyRateStore;
use App\Screening\Application\Query\PrecheckDraft;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Organizer view: paste a draft invite, get fixes before it is sent. */
final class PreCheckController extends AbstractController
{
    public function __construct(private readonly QueryBus $queries, private readonly HourlyRateStore $rate)
    {
    }

    #[Route('/precheck', name: 'precheck', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $draft = ['title' => '', 'description' => '', 'names' => '', 'attendeeCount' => 8, 'durationMin' => 60];
        $result = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('precheck', (string) $request->request->get('_token'))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
            $draft = [
                'title' => trim((string) $request->request->get('title')),
                'description' => trim((string) $request->request->get('description')),
                'names' => trim((string) $request->request->get('names')),
                'attendeeCount' => max(1, min(500, (int) $request->request->get('attendeeCount', 1))),
                'durationMin' => max(5, min(480, (int) $request->request->get('durationMin', 30))),
            ];
            $result = $this->queries->ask(new PrecheckDraft($draft['title'], $draft['description'], $draft['attendeeCount'], $draft['durationMin'], $draft['names']));
        }

        return $this->render('precheck/index.html.twig', ['draft' => $draft, 'result' => $result, 'rate' => $this->rate->cents() / 100]);
    }
}
