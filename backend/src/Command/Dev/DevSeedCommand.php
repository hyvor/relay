<?php

namespace App\Command\Dev;

use App\Api\Console\Authorization\Scope;
use App\Entity\Type\DomainStatus;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use App\Service\Instance\InstanceService;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\DebugIncomingEmailFactory;
use App\Tests\Factory\DnsRecordFactory;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFeedbackFactory;
use App\Tests\Factory\SendRecipientFactory;
use App\Tests\Factory\ServerFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SuppressionFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Hyvor\Internal\Sudo\SudoUserFactory;

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
        private InstanceService $instanceService
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

        SudoUserFactory::createOne(['user_id' => 1]);

        $systemProject = ProjectFactory::createOne([
            'user_id' => 1,
            'name' => 'System'
        ]);

        $instance = $this->instanceService->createInstance();

        ProjectUserFactory::createOne([
            'project' => $instance->getSystemProject(),
            'user_id' => 1,
            'scopes' => [
                Scope::PROJECT_READ,
                Scope::SENDS_READ,
                Scope::DOMAINS_READ,
                Scope::ANALYTICS_READ,
            ],
        ]);

        $transactionalQueue = QueueFactory::createTransactional();
        $distributionalQueue = QueueFactory::createDistributional();

        DnsRecordFactory::new()->a()->create();
        DnsRecordFactory::new()->mx()->create();

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay',
            'api_workers' => 2,
            'email_workers' => 2,
            'webhook_workers' => 1,
            'incoming_workers' => 1,
            'private_ip' => '127.0.0.1',
        ]);
        IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '0.0.0.0',
            'queue' => $transactionalQueue,
        ]);
        IpAddressFactory::createOne(
            ['server' => $server, 'queue' => $distributionalQueue]
        );

        $project = ProjectFactory::createOne([
            'name' => 'Test Project',
            'user_id' => 1,
        ]);
        ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
            'scopes' => Scope::all()
        ]);

        ApiKeyFactory::createOne([
            'project' => $project,
            'name' => 'Test API Key',
            'key_hashed' => hash('sha256', 'test-api-key')
        ]);

        DomainFactory::createOne(['project' => $project, 'domain' => 'hyvor.com']);
        $domain = DomainFactory::createOne(
            ['project' => $project, 'domain' => 'hyvor.local.testing', 'status' => DomainStatus::ACTIVE]
        );
        DomainFactory::createMany(15, ['project' => $project]);

        $sendsQueued = SendFactory::createMany(2, [
            'project' => $project,
            'domain' => $domain,
            'queued' => true,
        ]);
        $sendsSent = SendFactory::createMany(5, [
            'project' => $project,
            'domain' => $domain,
            'queued' => false,
        ]);

        $allSends = array_merge($sendsQueued, $sendsSent);
        foreach ($allSends as $send) {
            $types = SendRecipientType::cases();
            $typeKey = array_rand($types);
            $type = $types[$typeKey];

            foreach (range(1, rand(1,2)) as $i) {
                $recipient = SendRecipientFactory::new()
                    ->distribute('status', SendRecipientStatus::cases())
                    ->create([
                        'send' => $send,
                        'type' => $type,
                    ]);

                SendFeedbackFactory::createOne([
                    'sendRecipient' => $recipient[0],
                    'type' => SendFeedbackType::cases()[array_rand(SendFeedbackType::cases())],
                ]);
            }

            SendAttemptFactory::new()
                ->distribute('status', SendAttemptStatus::cases())
                ->create(['send' => $send]);

        }

        SuppressionFactory::createMany(16, [
            'project' => $project,
        ]);

        DebugIncomingEmailFactory::createMany(50);

        $output->writeln('<info>Database seeded with test data.</info>');

        return Command::SUCCESS;
    }

}
