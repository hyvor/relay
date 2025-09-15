<?php

namespace App\Service\ServerTask;

use App\Entity\Server;
use App\Entity\ServerTask;
use App\Entity\Type\ServerTaskType;
use App\Service\Server\ServerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ServerTaskService
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private ServerService $serverService,
    ) {
    }

    /**
     * Create a task for one, multiple or all servers
     * @param null|Server|Server[] $servers
     * @param array<string, mixed> $payload
     */
    public function createTaskFor(null|Server|array $servers, ServerTaskType $serverTaskType, array $payload): void
    {
        if ($servers === null) {
            $servers = $this->serverService->getServers();
        }
        if ($servers instanceof Server) {
            $servers = [$servers];
        }

        foreach ($servers as $server) {
            $task = new ServerTask();
            $task->setServer($server)
                ->setType($serverTaskType)
                ->setPayload($payload)
                ->setUpdatedAt($this->now())
                ->setCreatedAt($this->now());
            $this->em->persist($task);
        }

        $this->em->flush();
    }

    /**
     * @param Server|Server[]|null $servers
     */
    public function createUpdateStateTask(
        null|Server|array $servers,
        bool $apiWorkersUpdated = false,
    ): void {
        $this->createTaskFor(
            $servers,
            ServerTaskType::UPDATE_STATE,
            [
                'api_workers_updated' => $apiWorkersUpdated
            ]
        );
    }

    /**
     * @return ServerTask[]
     */
    public function getTaskForServer(Server $server): array
    {
        return $this->em->getRepository(ServerTask::class)->findBy(['server' => $server]);
    }

    public function deleteTask(ServerTask $task): void
    {
        $this->em->remove($task);
        $this->em->flush();
    }
}
