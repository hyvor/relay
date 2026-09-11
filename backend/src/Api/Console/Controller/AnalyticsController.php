<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\AnalyticsStatsInput;
use App\Entity\Project;
use App\Service\Send\SendAnalyticsService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{
    public function __construct(
        private SendAnalyticsService $sendAnalyticsService
    ) {
    }

    #[Route('/analytics/stats', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    #[OA\Get(
        summary: 'Get analytics stats',
        description: 'Returns the total sends count, bounce rate, and complaint rate for the specified period.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Analytics stats for the period',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'sends', type: 'integer'),
                new OA\Property(property: 'bounce_rate', type: 'number', format: 'float'),
                new OA\Property(property: 'complaint_rate', type: 'number', format: 'float'),
            ]
        )
    )]
    public function stats(
        Project $project,
        #[MapQueryString] AnalyticsStatsInput $input
    ): JsonResponse {
        [
            'total' => $total,
            'bounced' => $bounced,
            'complained' => $complained
        ] = $this->sendAnalyticsService->getCountsByPeriod($project, $input->period);

        return new JsonResponse([
            'sends' => $total,
            'bounce_rate' => $total > 0 ? ($bounced / $total) : 0.0,
            'complaint_rate' => $total > 0 ? ($complained / $total) : 0.0,
        ]);
    }

    #[Route('/analytics/sends/chart', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    #[OA\Get(
        summary: 'Get sends chart data',
        description: 'Returns daily send counts (total, bounced, complained, accepted, queued) for the last 30 days.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Daily sends chart data',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'bounced', type: 'integer'),
                    new OA\Property(property: 'complained', type: 'integer'),
                    new OA\Property(property: 'accepted', type: 'integer'),
                    new OA\Property(property: 'queued', type: 'integer'),
                ]
            )
        )
    )]
    public function sendsChart(Project $project): JsonResponse
    {
        $data = $this->sendAnalyticsService->getSendsChartData($project);
        return new JsonResponse($data);
    }
}
