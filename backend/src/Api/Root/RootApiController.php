<?php

namespace App\Api\Root;

use App\Service\Instance\InstanceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class RootApiController
{

    public function __construct(private InstanceService $instanceService)
    {
    }

    #[Route('/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        // to force database connection
        $this->instanceService->getInstance();

        return new JsonResponse(['status' => 'ok']);
    }

}