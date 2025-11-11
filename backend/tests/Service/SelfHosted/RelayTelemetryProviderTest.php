<?php

namespace App\Tests\Service\SelfHosted;

use App\Entity\Type\DomainStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\SelfHosted\RelayTelemetryProvider;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Hyvor\Internal\Auth\Oidc\OidcUserFactory;

#[CoversClass(RelayTelemetryProvider::class)]
class RelayTelemetryProviderTest extends KernelTestCase
{

    public function test_gets_telemetry_data(): void
    {
        // ======= Add Data
        $instance = InstanceFactory::createOne();

        $server1 = ServerFactory::createOne([
            'api_workers' => 2,
            'email_workers' => 3,
            'incoming_workers' => 4,
            'webhook_workers' => 5,
        ]);
        $server2 = ServerFactory::createOne([
            'api_workers' => 1,
            'email_workers' => 1,
            'incoming_workers' => 1,
            'webhook_workers' => 1,
        ]);

        $ip1 = IpAddressFactory::createOne(['server' => $server1]);

        OidcUserFactory::createMany(3);
        $projects = ProjectFactory::createMany(4);

        $project1 = $projects[0];
        $domains = DomainFactory::createMany(3, ['project' => $project1, 'status' => DomainStatus::ACTIVE]);
        DomainFactory::createMany(2, ['project' => $project1, 'status' => DomainStatus::WARNING]);

        $domain1 = $domains[0];
        $send1 = SendFactory::createOne(
            ['project' => $project1, 'domain' => $domain1, 'created_at' => new \DateTimeImmutable('-1 hours')]
        );

        $send1RAccepted = SendRecipientFactory::createMany(2, [
            'send' => $send1,
            'status' => SendRecipientStatus::ACCEPTED
        ]);
        $send1RBounced = SendRecipientFactory::createMany(3, [
            'send' => $send1,
            'status' => SendRecipientStatus::BOUNCED
        ]);
        $send1RComplained = SendRecipientFactory::createMany(1, [
            'send' => $send1,
            'status' => SendRecipientStatus::COMPLAINED
        ]);
        $send1RFailed = SendRecipientFactory::createMany(1, [
            'send' => $send1,
            'status' => SendRecipientStatus::FAILED
        ]);
        $send1RSuppressed = SendRecipientFactory::createMany(1, [
            'send' => $send1,
            'status' => SendRecipientStatus::SUPPRESSED
        ]);

        $sendOutside24h = SendFactory::createOne(
            ['project' => $project1, 'domain' => $domain1, 'created_at' => new \DateTimeImmutable('-2 days')]
        );

        // ======= DO
        $service = $this->getService(RelayTelemetryProvider::class);
        $service->initialize();

        // ======= ASSERT
        $payload = $service->getPayload();
        $this->assertEquals($instance->getUuid(), $service->getInstanceUuid());
        $this->assertEquals('0.0.0', $service->getVersion());
        $this->assertEquals(2, $payload['servers_count']);
        $this->assertEquals(1, $payload['ip_addresses_count']);
        $this->assertEquals(2, $payload['queues_count']); // from sends
        $this->assertEquals(3, $payload['workers_api_count']);
        $this->assertEquals(4, $payload['workers_email_count']);
        $this->assertEquals(5, $payload['workers_incoming_count']);
        $this->assertEquals(6, $payload['workers_webhook_count']);
        $this->assertEquals(3, $payload['users_count']);
        $this->assertEquals(5, $payload['projects_count']); // 4 created + 1 system project
        $this->assertEquals(5, $payload['domains_count']);
        $this->assertEquals(3, $payload['domains_active_count']);
        $this->assertEquals(1, $payload['sends_24h_count']);
        $this->assertEquals(8, $payload['recipients_24h_count']);
        $this->assertEquals(2, $payload['recipients_24h_accepted_count']);
        $this->assertEquals(3, $payload['recipients_24h_bounced_count']);
        $this->assertEquals(1, $payload['recipients_24h_complained_count']);
        $this->assertEquals(1, $payload['recipients_24h_failed_count']);
        $this->assertEquals(1, $payload['recipients_24h_suppressed_count']);
    }

}