<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\ServerObject;
use App\Service\Server\ServerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ServerController extends AbstractController
{

    public function __construct(
        private ServerService $serverService
    )
    {
    }

    #[Route('/servers', methods: 'GET')]
    public function getServers(): JsonResponse
    {
        $servers = $this->serverService->getServers();
        
        $serverObjects = array_map(
            fn($server) => new ServerObject($server),
            $servers
        );

        return $this->json($serverObjects);
    }

}