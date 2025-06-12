<?php

namespace App\Service\Server;

use App\Config;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ServerService
{

    use ClockAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Config $config,
    )
    {
    }

    /**
     * @return Server[]
     */
    public function getServers(): array
    {
        return $this->em->getRepository(Server::class)->findAll();
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
            ->setApiWorkers(Cpu::getCores() * 2);

        $this->em->persist($server);
        $this->em->flush();

        return $server;
    }

    public function updateServerFromConfig(Server $server): void
    {
        // todo
    }

}