<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\QueueObject;
use App\Service\Queue\QueueService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class QueueController extends AbstractController
{

    public function __construct(
        private QueueService $queueService
    ) {}

    #[Route('/queues', methods: 'GET')]
    public function getQueues(): JsonResponse
    {
        $queues = $this->queueService->getAllQueues();

        $queueObjects = array_map(
            fn($queue) => new QueueObject($queue),
            $queues
        );

        return $this->json($queueObjects);
    }
}
