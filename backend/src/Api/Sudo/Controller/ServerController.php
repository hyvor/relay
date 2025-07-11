<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\UpdateServerInput;
use App\Api\Sudo\Object\ServerObject;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    #[Route('/servers/{id}', methods: 'PATCH')]
    public function updateServer(int $id, #[MapRequestPayload] UpdateServerInput $input): JsonResponse
    {
        $server = $this->serverService->getServerById($id);
        
        if ($server === null) {
            throw new NotFoundHttpException('Server not found');
        }

        $updates = new UpdateServerDto();
        
        if ($input->hasProperty('api_workers')) {
            $updates->apiWorkers = $input->api_workers;
        }
        
        if ($input->hasProperty('email_workers')) {
            $updates->emailWorkers = $input->email_workers;
        }
        
        if ($input->hasProperty('webhook_workers')) {
            $updates->webhookWorkers = $input->webhook_workers;
        }

        $this->serverService->updateServer($server, $updates);

        return $this->json(new ServerObject($server));
    }

    #[Route('/servers/{id}/re-init', methods: 'POST')]
    public function reInitServer(int $id): JsonResponse
    {
        $server = $this->serverService->getServerById($id);

        if ($server === null) {
            throw new NotFoundHttpException('Server not found');
        }

        // TODO: Re-initialize server logic here

        return $this->json(new ServerObject($server));
    }
}
