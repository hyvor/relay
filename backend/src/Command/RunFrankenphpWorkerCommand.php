<?php

namespace App\Command;

use App\Service\Server\ServerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @codeCoverageIgnore
 */
#[AsCommand(
    name: 'app:run:frankenphp-worker',
    description: 'Runs the FrankenPHP worker processes.'
)]
class RunFrankenphpWorkerCommand extends Command
{
    public function __construct(
        private ServerService $serverService,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentServer =  $this->serverService->getServerByCurrentHostname();

        if (!$currentServer) {
            $output->writeln('<error>Current server not found in the database. Ensure the hostname is correctly configured.</error>');
            return Command::FAILURE;
        }

        $process = new Process(
            [
                'frankenphp',
                'run',
                '--config',
                '/etc/caddy/Caddyfile',
            ],
            env: [
                'WORKERS' => $currentServer->getApiWorkers(),
            ]
        );

        $output->writeln('<info>Starting FrankenPHP with WORKERS=' . $currentServer->getApiWorkers() . '</info>');

        $process->setTimeout(null);
        $process->run(function ($type, $buffer): void {
            if ($type === Process::OUT) {
                echo $buffer;
            } else {
                fwrite(STDERR, $buffer);
            }
        });

        return Command::SUCCESS;
    }
}
