<?php

namespace App\Command\Dev;

use App\Service\Instance\InstanceService;
use App\Entity\Type\SendStatus;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\ServerFactory;
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

        InstanceFactory::createOne([
            'domain' => InstanceService::DEFAULT_DOMAIN
        ]);

        $transactionalQueue = QueueFactory::createTransactional();
        $distributionalQueue = QueueFactory::createDistributional();

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay'
        ]);
        IpAddressFactory::createOne(['server' => $server, 'queue' => $transactionalQueue, 'is_available' => true, 'is_enabled' => true]);
        IpAddressFactory::createOne(['server' => $server, 'queue' => $distributionalQueue, 'is_available' => true, 'is_enabled' => true]);

        $domain = DomainFactory::createOne(['domain' => 'hyvor.com']);
        $domain = DomainFactory::createOne(['domain' => 'hyvor.local.testing']);
        $project = ProjectFactory::createOne([
            'name' => 'Test Project',
            'hyvor_user_id' => 1,
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
