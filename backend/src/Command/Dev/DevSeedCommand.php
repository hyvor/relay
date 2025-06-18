<?php

namespace App\Command\Dev;

use App\Entity\Type\SendStatus;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @codeCoverageIgnore
 */
#[AsCommand(
    name: 'app:dev:seed',
    description: 'Seeds the database with test data for development purposes.'
)]
class DevSeedCommand extends Command
{

    public function __construct(
        private KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->kernel->getEnvironment();
        if ($env !== 'dev' && $env !== 'test') {
            $output->writeln('<error>This command can only be run in the dev and test environments.</error>');
            return Command::FAILURE;
        }

        QueueFactory::createTransactional();
        $domain = DomainFactory::createOne(['domain' => 'example.com']);
        $project = ProjectFactory::createOne([
            'name' => 'Test Project',
        ]);

        $sends_queued = SendFactory::createMany(2, [
            'project' => $project,
            'domain' => $domain,
            'status' => SendStatus::QUEUED,
        ]);

        $sends_sent = SendFactory::createMany(5, [
            'project' => $project,
            'domain' => $domain,
            'sent_at' => new \DateTimeImmutable(),
            'status' => SendStatus::SENT,
        ]);

        $sent_failed = SendFactory::createMany(1, [
            'project' => $project,
            'domain' => $domain,
            'failed_at' => new \DateTimeImmutable(),
            'status' => SendStatus::FAILED,
        ]);

        $output->writeln('<info>Database seeded with test data.</info>');

        return Command::SUCCESS;
    }

}
