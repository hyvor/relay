<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\AnalyticsStatsInput;
use App\Entity\Project;
use App\Service\Send\SendAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{

    public function __construct(
        private SendAnalyticsService $sendAnalyticsService
    )
    {
    }

    // gets:
    // - sends count for specified period (30d, 7d, 24h)
    // - bounce rate for specified period
    // - complaint rate for specified period
    #[Route('/analytics/stats', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    public function getStats(
        Project $project,
        #[MapQueryString] AnalyticsStatsInput $input
    ): JsonResponse {
        [
            'total' => $total,
            'bounced' => $bounced,
            'complained' => $complained
        ] = $this->sendAnalyticsService->getCountsByPeriod($project, $input->period);

        return new JsonResponse([
            'sends_stats' => $total,
            'bounce_rate_stats' => $total > 0 ? ($bounced / $total) : 0.0,
            'complaint_rate_stats' => $total > 0 ? ($complained / $total) : 0.0,
        ]);
    }

    #[Route('/analytics/sends/chart', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    public function getSendsChartData(Project $project): JsonResponse
    {
        $data = $this->sendAnalyticsService->getSendsChartData($project);
        return new JsonResponse($data);
    }

}