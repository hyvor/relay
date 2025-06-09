<?php

namespace App\Command\Dev;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:dev:reset',
    description: 'Resets the database, runs the migrations again, and seeds.'
)]
class DevResetCommand extends Command
{

    public function __construct(
        private KernelInterface $kernel
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption('seed', null, InputOption::VALUE_NONE, 'Seed the database after resetting it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $env = $this->kernel->getEnvironment();
        if ($env !== 'dev' && $env !== 'test') {
            $output->writeln('<error>This command can only be run in the dev and test environments.</error>');
            return Command::FAILURE;
        }

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $application->run(
            new ArrayInput([
                'command' => 'doctrine:database:drop',
                '--force' => true,
            ]),
            $output
        );

        $application->run(
            new ArrayInput([
                'command' => 'doctrine:database:create',
            ]),
            $output
        );

        $application->run(
            new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]),
            $output
        );

        if ($input->getOption('seed')) {
            $application->run(
                new ArrayInput([
                    'command' => 'app:dev:seed',
                ]),
                $output
            );
        }

        return Command::SUCCESS;
    }

}