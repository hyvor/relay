<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\UpdateServerInput;
use App\Api\Sudo\Object\ServerObject;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    public function updateServer(
        int $id,
        #[MapRequestPayload] UpdateServerInput $input
    ): JsonResponse
    {
        $server = $this->serverService->getServerById($id);

        if (!$server) {
            throw new BadRequestHttpException('Server with ID ' . $id . ' does not exist.');
        }

        $updates = new UpdateServerDto();

        if ($input->apiWorkersSet) {
            $updates->apiWorkers = $input->api_workers;
        }
        if ($input->emailWorkersSet) {
            $updates->emailWorkers = $input->email_workers;
        }
        if ($input->webhookWorkersSet) {
            $updates->webhookWorkers = $input->webhook_workers;
        }

        $this->serverService->updateServer($server, $updates);

        return $this->json(new ServerObject($server));
    }

}