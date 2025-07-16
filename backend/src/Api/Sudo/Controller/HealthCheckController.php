<?php

namespace App\Api\Sudo\Controller;

use App\Service\Management\Health\HealthCheckService;
use App\Service\Instance\InstanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController extends AbstractController
{
    public function __construct(
        private HealthCheckService $healthCheckService,
        private InstanceService $instanceService,
    ) {
    }

    #[Route('/health-checks', methods: 'POST')]
    public function runHealthChecks(): JsonResponse
    {
        // Run all health checks
        $this->healthCheckService->runAllHealthChecks();

        $instance = $this->instanceService->getInstance();
        return new JsonResponse($instance->getHealthCheckResults());
    }
}
