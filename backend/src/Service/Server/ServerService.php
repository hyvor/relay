<?php

namespace App\Service\Server;

use App\Config;
use App\Entity\Server;
use App\Service\Docker\DockerService;
use App\Service\Server\Dto\UpdateServerDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ServerService
{

    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Config $config,
        private readonly DockerService $dockerService
    )
    {
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

    public function createServerFromConfig(): Server
    {

        $server = new Server();
        $server
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setHostname($this->config->getHostname())
            ->setDockerHostname($this->dockerService->getDockerHostname())
            ->setApiWorkers(Cpu::getCores() * 2);

        $this->em->persist($server);
        $this->em->flush();

        return $server;
    }

    public function updateServer(Server $server, UpdateServerDto $updates): void
    {
        if ($updates->lastPingAtSet) {
            $server->setLastPingAt($updates->lastPingAt);
        }

        $server->setUpdatedAt($this->now());

        $this->em->persist($server);
        $this->em->flush();
    }

}