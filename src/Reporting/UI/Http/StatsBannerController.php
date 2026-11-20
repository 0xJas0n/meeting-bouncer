<?php

declare(strict_types=1);

namespace App\Reporting\UI\Http;

use App\Reporting\Application\Query\GetWeeklyReport;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/** Rendered as a fragment from the base template: `{{ render(controller(...)) }}`. */
final class StatsBannerController extends AbstractController
{
    public function __construct(private readonly QueryBus $queries)
    {
    }

    public function __invoke(): Response
    {
        return $this->render('reporting/_banner.html.twig', ['report' => $this->queries->ask(new GetWeeklyReport())]);
    }
}
