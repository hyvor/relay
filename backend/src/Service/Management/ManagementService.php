<?php

namespace App\Service\Management;

use App\Entity\Server;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Queue\QueueService;
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
    )
    {
        $this->output = new NullOutput();
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function initialize(): void
    {
        $this->entityManager->wrapInTransaction(function() {
            $this->initializeInstance();
            $server = $this->initializeServer();
            $this->initializeIpAddresses($server);
            $this->initializeDefaultQueues();
        });
    }

    private function initializeInstance(): void
    {

        $instance = $this->instanceService->tryGetInstance();

        if ($instance === null) {
            $this->output->writeln('<info>Initiating the instance...</info>');
            $this->instanceService->createInstance();
        }

    }

    private function initializeServer(): Server
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->output->writeln('<info>Creating new server entry in the database...</info>');
            $server = $this->serverService->createServerFromConfig();
            $this->output->writeln('<info>New server entry created successfully.</info>');
        }

        $this->output->writeln(sprintf('<info>Server ID: %d</info>', $server->getId()));
        $this->output->writeln(sprintf('<info>Server Hostname: %s</info>', $server->getHostname()));
        $this->output->writeln(sprintf('<info>Server Docker Hostname: %s</info>', $server->getDockerHostname()));

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
            $this->output->writeln('<info>Default queues already being created by another process.</info>');
            return;
        }

        $this->output->writeln('<info>Creating default queues...</info>');
        $this->queueService->createDefaultQueues();
        $this->output->writeln('<info>Default queues created successfully.</info>');

        $lock->release();

    }

}