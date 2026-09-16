<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\GetServersInput;
use App\Api\Sudo\Input\UpdateServerInput;
use App\Api\Sudo\Object\ServerObject;
use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\App\Config;
use App\Service\Ip\WarmupScheduleService;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class ServerController extends AbstractController
{

    public function __construct(
        private ServerService $serverService,
        private WarmupScheduleService $warmupScheduleService,
        private Config $appConfig,
    ) {}

    #[Route('/servers', methods: 'GET')]
    public function getServers(#[MapQueryString] GetServersInput $input): JsonResponse
    {
        $servers = $this->serverService->getServers($input->search);

        /** @var IpAddress[] $ipAddresses */
        $ipAddresses = [];
        foreach ($servers as $server) {
            foreach ($server->getIpAddresses() as $ipAddress) {
                $ipAddresses[] = $ipAddress;
            }
        }
        $warmupSchedules = $this->warmupScheduleService->getCurrentWarmupSchedulesByIpAddresses($ipAddresses);

        $serverObjects = array_map(
            fn(Server $server) => new ServerObject(
                $server,
                $this->appConfig->getInstanceDomain(),
                $warmupSchedules,
            ),
            $servers
        );

        return $this->json($serverObjects);
    }

    #[Route('/servers/{id}', methods: 'PATCH')]
    public function updateServer(
        int $id,
        #[MapRequestPayload] UpdateServerInput $input
    ): JsonResponse {
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
        if ($input->incomingWorkersSet) {
            $updates->incomingWorkers = $input->incoming_workers;
        }

        $this->serverService->updateServer($server, $updates, createUpdateStateTask: true);

        return $this->json(new ServerObject($server, $this->appConfig->getInstanceDomain()));
    }
}
