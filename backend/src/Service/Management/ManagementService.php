<?php

namespace App\Service\Management;

use App\Config;
use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\Ip\IpAddressService;
use App\Service\Server\ServerService;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ManagementService
{

    private OutputInterface $output;

    public function __construct(
        private ServerService $serverService,
        private IpAddressService $ipAddressService,
        private Config $config,
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
        $server = $this->initializeServer();
        $this->initializeIpAddresses($server);
    }

    private function initializeServer(): Server
    {
        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->output->writeln('<info>Creating new server entry in the database...</info>');
            $server = $this->serverService->createServerFromConfig();
            $this->output->writeln('<info>New server entry created successfully.</info>');
        } else {
            $this->output->writeln([
                '<info>Updating existing server entry in the database...</info>',
                sprintf('   Server ID: %d', $server->getId()),
                sprintf('   Hostname: %s', $server->getHostname()),
                $this->outputUpdatingOn('API', $this->config->isApiOn(), $server->getApiOn()),
                $this->outputUpdatingOn('Email', $this->config->isEmailOn(), $server->getEmailOn()),
                $this->outputUpdatingOn('Webhook', $this->config->isWebhookOn(), $server->getWebhookOn()),
                ''
            ]);

            $server = $this->serverService->updateServerFromConfig($server);

            $this->output->writeln('<info>Server entry updated successfully.</info>');
        }

        $this->output->writeln(sprintf('<info>Server ID: %d</info>', $server->getId()));

        return $server;
    }

    private function initializeIpAddresses(Server $server): void
    {
        $this->output->writeln('<info>Initializing IP addresses for the server...</info>');
        $this->ipAddressService->updateIpAddressesOfServer($server);
        $this->output->writeln('<info>IP addresses initialized successfully.</info>');
    }

    private function outputUpdatingOn(string $name, bool $newValue, bool $oldValue): string
    {
        return sprintf(
            '   %s On: %s (previous: %s)',
            $name,
            $newValue ? 'Yes' : 'No',
            $oldValue ? 'Yes' : 'No'
        );
    }

}