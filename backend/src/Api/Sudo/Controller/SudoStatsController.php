<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\SudoStatsInput;
use App\Service\Stats\SudoAnalyticsService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class SudoStatsController extends AbstractController
{
    public function __construct(
        private SudoAnalyticsService $sudoAnalyticsService,
    ) {
    }

    #[Route('/stats', methods: 'GET')]
    public function getStats(
        #[MapQueryString] SudoStatsInput $input
    ): JsonResponse {
        $stats = $this->sudoAnalyticsService->getStats($input->period);
        return new JsonResponse($stats);
    }
}
