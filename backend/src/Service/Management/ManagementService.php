<?php

namespace App\Service\Management;

use App\Entity\Instance;
use App\Entity\Server;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\ServerIp;
use App\Service\Queue\QueueService;
use App\Service\Server\Dto\UpdateServerDto;
use App\Service\Server\ServerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

class ManagementService
{

    private OutputInterface $output;

    public function __construct(
        private ServerService $serverService,
        private InstanceService $instanceService,
        private IpAddressService $ipAddressService,
        private QueueService $queueService,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
        private ServerIp $serverIp
    ) {
        $this->output = new NullOutput();
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function initialize(): void
    {
        $this->entityManager->wrapInTransaction(function () {
            $instance = $this->initializeInstance();
            $this->initializeDefaultQueues();
            $server = $this->initializeServer($instance);
            $this->initializeIpAddresses($server);
        });
    }

    private function initializeInstance(): Instance
    {
        $instance = $this->instanceService->tryGetInstance();

        if ($instance === null) {
            $this->output->writeln('<info>Initiating the instance...</info>');
            $instance = $this->instanceService->createInstance();
        }

        return $instance;
    }

    private function initializeServer(Instance $instance): Server
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->output->writeln('<info>Creating new server entry in the database...</info>');
            $server = $this->serverService->createServerFromConfig();
            $this->output->writeln('<info>New server entry created successfully.</info>');
        }

        $privateIp = $this->serverIp->getPrivateIp($instance->getPrivateNetworkCidr());
        if ($privateIp !== $server->getPrivateIp()) {
            $updateDto = new UpdateServerDto();
            $updateDto->privateIp = $privateIp;
            $this->serverService->updateServer($server, $updateDto);
        }

        $this->output->writeln(sprintf('<info>Server ID: %d</info>', $server->getId()));
        $this->output->writeln(sprintf('<info>Server Hostname: %s</info>', $server->getHostname()));
        $this->output->writeln(sprintf('<info>Server Docker Hostname: %s</info>', $server->getHostname()));
        $this->output->writeln(sprintf('<info>Server Private IP: %s</info>', $privateIp));

        return $server;
    }

    private function initializeIpAddresses(Server $server): void
    {
        $this->output->writeln('<info>Initializing IP addresses for the server...</info>');
        $this->ipAddressService->updateIpAddressesOfServer($server);
        $this->output->writeln('<info>IP addresses initialized successfully.</info>');
    }

    private function initializeDefaultQueues(): void
    {
        $hasDefaultQueues = $this->queueService->hasDefaultQueues();

        if ($hasDefaultQueues) {
            return;
        }

        $lock = $this->lockFactory->createLock('management_init_default_queues');

        if (!$lock->acquire()) {
            $this->output->writeln('<info>Default queues are already being created by another process.</info>');
            return;
        }

        $this->output->writeln('<info>Creating default queues...</info>');
        $this->queueService->createDefaultQueues();
        $this->output->writeln('<info>Default queues created successfully.</info>');

        $lock->release();
    }

}
