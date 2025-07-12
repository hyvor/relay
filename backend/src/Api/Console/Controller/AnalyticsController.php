<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Entity\Project;
use App\Service\Send\SendAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{

    public function __construct(
        private SendAnalyticsService $sendAnalyticsService
    )
    {
    }

    // gets:
    // - sends count last 30 days
    // - bounce rate (last 30 days)
    // - complaint rate (last 30 days)
    #[Route('/analytics/stats', methods: 'GET')]
    #[ScopeRequired(Scope::ANALYTICS_READ)]
    public function getStats(Project $project): JsonResponse
    {
        [
            'total' => $total,
            'bounced' => $bounced,
            'complained' => $complained
        ] = $this->sendAnalyticsService->getLast30dCounts($project);

        return new JsonResponse([
            'sends_30d' => $total,
            'bounce_rate_30d' => $total > 0 ? ($bounced / $total) : 0.0,
            'complaint_rate_30d' => $total > 0 ? ($complained / $total) : 0.0,
        ]);
    }

}