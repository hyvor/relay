<?php

namespace App\Service\Management;

use App\Service\Config;
use App\Service\Server\ServerService;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ManagementService
{

    private OutputInterface $output;

    public function __construct(
        private ServerService $serverService,
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

        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            $this->output->writeln('<info>Creating new server entry in the database...</info>');
            $this->serverService->createServerFromConfig();
        } else {
            $this->output->writeln('<info>Updating existing server entry in the database...</info>');

            $this->output->writeln(sprintf('<info>Server ID: %d</info>', $server->getId()));
            $this->output->writeln(sprintf('<info>Hostname: %s</info>', $server->getHostname()));
            $this->outputUpdatingOn('API', $this->config->isApiOn(), $server->getApiOn());
            $this->outputUpdatingOn('Email', $this->config->isEmailOn(), $server->getEmailOn());
            $this->outputUpdatingOn('Webhook', $this->config->isWebhookOn(), $server->getWebhookOn());

            $this->serverService->updateServerFromConfig($server);
        }

    }

    private function outputUpdatingOn(string $name, bool $newValue, bool $oldValue): void
    {
        $this->output->writeln(sprintf(
            '<info>%s On: %s (old: %s)</info>',
            $name,
            $newValue ? 'Yes' : 'No',
            $oldValue ? 'Yes' : 'No'
        ));
    }

}