<?php

namespace App\Api\Sudo\Controller;

use App\Service\Log\LogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class LogController extends AbstractController
{

    public function __construct(
        private LogService $logService
    )
    {
    }

    #[Route('/logs', methods: 'GET')]
    public function getLogs(): JsonResponse
    {
        $logs = $this->logService->readLogs();
        return new JsonResponse($logs);
    }

}