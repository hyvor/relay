<?php

namespace App\Command;

use App\Service\App\Config;
use App\Service\Server\ServerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @codeCoverageIgnore
 */
#[AsCommand(
    name: 'frankenphp:run',
    description: 'Runs the FrankenPHP worker processes.'
)]
class FrankenphpRunCommand extends Command
{
    public function __construct(
        private ServerService $serverService,
        private Config $config,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption(
            'watch',
            null,
            InputOption::VALUE_NONE,
            'Enable watch mode for automatic reloads on code changes.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentServer = $this->serverService->getServerByCurrentHostname();

        if (!$currentServer) {
            $hostname = $this->config->getHostname();
            $output->writeln(
                "<error>Current server not found in the database (Hostname: $hostname)</error>"
            );
            return Command::FAILURE;
        }

        $cmd = [
            'frankenphp',
            'run',
            '--config',
            '/etc/caddy/Caddyfile',
        ];

        if ($input->getOption('watch')) {
            $cmd[] = '--watch';
        }

        $process = new Process(
            $cmd,
            env: [
                'WORKERS' => $currentServer->getApiWorkers(),
            ]
        );

        $output->writeln('<info>Starting FrankenPHP with WORKERS=' . $currentServer->getApiWorkers() . '</info>');

        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::OUT) {
                echo $buffer;
            } else {
                fwrite(STDERR, $buffer);
            }
        });

        return Command::SUCCESS;
    }
}
