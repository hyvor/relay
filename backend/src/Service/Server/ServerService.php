<?php

namespace App\Service\Server;

use App\Entity\Server;
use App\Service\App\Config;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\Event\ServerUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ServerService
{

    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $ed,
        private readonly Config $config,
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
        bool $createUpdateStateTask = false,
    ): void {
        $serverOld = clone $server;

        if ($updates->lastPingAtSet) {
            $server->setLastPingAt($updates->lastPingAt);
        }
        if ($updates->apiWorkersSet) {
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

        $event = new ServerUpdatedEvent($serverOld, $server, $updates, $createUpdateStateTask);
        $this->ed->dispatch($event);
    }


    /**
     * @return array<string, int>
     */
    public function getAllWorkersCounts(): array
    {
        $conn = $this->em->getConnection();
        $sql = 'SELECT
            SUM(api_workers) AS api_workers,
            SUM(email_workers) AS email_workers,
            SUM(incoming_workers) AS incoming_workers,
            SUM(webhook_workers) AS webhook_workers
        FROM servers';
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        /** @var array<string, ?int> $data */
        $data = $result->fetchAssociative();

        return [
            'api_workers' => $data['api_workers'] ?? 0,
            'email_workers' => $data['email_workers'] ?? 0,
            'incoming_workers' => $data['incoming_workers'] ?? 0,
            'webhook_workers' => $data['webhook_workers'] ?? 0,
        ];
    }

}
