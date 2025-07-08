<?php

namespace App\Command\Dev;

use App\Service\Domain\DomainService;
use App\Service\Instance\InstanceService;
use App\Entity\Type\SendStatus;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\ServerFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SuppressionFactory;
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

        InstanceFactory::new()->withDefaultDkim()->create([
            'domain' => InstanceService::DEFAULT_DOMAIN
        ]);

        $transactionalQueue = QueueFactory::createTransactional();
        $distributionalQueue = QueueFactory::createDistributional();

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay'
        ]);
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '0.0.0.0',
            'queue' => $transactionalQueue,
            'is_available' => true,
            'is_enabled' => true
        ]);
        IpAddressFactory::createOne(['server' => $server, 'queue' => $distributionalQueue, 'is_available' => true, 'is_enabled' => true]);

        $project = ProjectFactory::createOne([
            'name' => 'Test Project',
            'hyvor_user_id' => 1,
        ]);

        DomainFactory::createOne(['project' => $project, 'domain' => 'hyvor.com']);
        $domain = DomainFactory::createOne(['project' => $project, 'domain' => 'hyvor.local.testing', 'dkim_verified' => true]);

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

        SuppressionFactory::createMany(16, [
            'project' => $project,
        ]);

        $output->writeln('<info>Database seeded with test data.</info>');

        return Command::SUCCESS;
    }

}
