<?php

namespace App\Service\Server;

use App\Entity\Server;
use App\Entity\Type\ServerTaskType;
use App\Service\App\Config;
use App\Service\Server\Dto\UpdateServerDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ServerService
{

    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Config $config,
        private readonly ServerTaskService $serverTaskService,
    ) {
    }

    /**
     * @return Server[]
     */
    public function getServers(): array
    {
        return $this->em->getRepository(Server::class)->findBy([], orderBy: ['id' => 'ASC']);
    }

    public function getServersCount(): int
    {
        return $this->em->getRepository(Server::class)->count();
    }

    public function isServerLeader(Server $server): bool
    {
        $firstServer = $this->em->getRepository(Server::class)->findOneBy([], orderBy: ['id' => 'ASC']);
        return $firstServer?->getId() === $server->getId();
    }

    public function getServerByCurrentHostname(): ?Server
    {
        return $this->getServerByHostname($this->config->getHostname());
    }

    public function getServerByHostname(string $hostname): ?Server
    {
        return $this->em->getRepository(Server::class)->findOneBy(['hostname' => $hostname]);
    }

    public function getServerById(int $id): ?Server
    {
        return $this->em->getRepository(Server::class)->find($id);
    }

    public function createServerFromConfig(): Server
    {
        $server = new Server();
        $server
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setLastPingAt($this->now())
            ->setHostname($this->config->getHostname())
            ->setApiWorkers(min(Cpu::getCores() * 2, 8))
            ->setEmailWorkers(4)
            ->setWebhookWorkers(2)
            ->setIncomingWorkers(1);

        $this->em->persist($server);
        $this->em->flush();

        return $server;
    }

    public function updateServer(
        Server $server,
        UpdateServerDto $updates,
        bool $updateStateCall = false,
    ): void {
        if ($updates->lastPingAtSet) {
            $server->setLastPingAt($updates->lastPingAt);
        }

        $apiWorkersUpdated = $updates->apiWorkersSet;
        if ($apiWorkersUpdated) {
            $server->setApiWorkers($updates->apiWorkers);
        }
        if ($updates->emailWorkersSet) {
            $server->setEmailWorkers($updates->emailWorkers);
        }
        if ($updates->webhookWorkersSet) {
            $server->setWebhookWorkers($updates->webhookWorkers);
        }
        if ($updates->incomingWorkersSet) {
            $server->setIncomingWorkers($updates->incomingWorkers);
        }

        $server->setUpdatedAt($this->now());

        $this->em->persist($server);
        $this->em->flush();

        if ($updateStateCall) {
            $this->serverTaskService->createTask(
                $server,
                ServerTaskType::UPDATE_STATE,
                $apiWorkersUpdated ? [
                    'api_workers_updated' => true
                ] : []
            );
        }
    }

}
