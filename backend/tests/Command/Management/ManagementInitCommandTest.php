<?php

namespace App\Tests\Command\Management;

use App\Command\Management\ManagementInitCommand;
use App\Entity\Instance;
use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Server;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\ServerIp;
use App\Service\Management\ManagementService;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
use Hyvor\Internal\Util\Crypt\Encryption;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ManagementInitCommand::class)]
#[CoversClass(ManagementService::class)]
#[CoversClass(ServerService::class)]
#[CoversClass(InstanceService::class)]
#[CoversClass(IpAddressService::class)]
class ManagementInitCommandTest extends KernelTestCase
{

    public function test_creates_instance_server_and_adds_ips(): void
    {

        $serverIpMock = $this->createMock(ServerIp::class);
        $serverIpMock->method('getPrivateIp')->willReturn('10.0.0.1');
        $serverIpMock->method('getPublicV4IpAddresses')->willReturn([
            '8.8.8.8',
            '9.9.9.9'
        ]);
        $this->container->set(ServerIp::class, $serverIpMock);

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        // INSTANCE
        $instance = $this->em->getRepository(Instance::class)->findAll();
        $this->assertCount(1, $instance);
        $instance = $instance[0];
        $this->assertSame('relay.hyvor.localhost', $instance->getDomain());
        $this->assertStringContainsString('---BEGIN PUBLIC KEY---', $instance->getDkimPublicKey());

        $privateKeyEncrypted = $instance->getDkimPrivateKeyEncrypted();
        $encryption = $this->container->get(Encryption::class);
        assert($encryption instanceof Encryption);
        $privateKey = $encryption->decryptString($privateKeyEncrypted);
        $this->assertStringContainsString('---BEGIN PRIVATE KEY---', $privateKey);

        // SERVERS
        $servers = $this->em->getRepository(Server::class)->findAll();
        $this->assertCount(1, $servers);
        $server = $servers[0];
        $this->assertSame('hyvor-relay', $server->getHostname());
        $this->assertSame('10.0.0.1', $server->getPrivateIp());

        $ips = $this->em->getRepository(IpAddress::class)->findBy(['server' => $server]);
        $this->assertCount(2, $ips);

        $this->assertSame('8.8.8.8', $ips[0]->getIpAddress());
        $this->assertTrue($ips[0]->getIsAvailable());
        $this->assertSame('9.9.9.9', $ips[1]->getIpAddress());
        $this->assertTrue($ips[1]->getIsAvailable());

    }

    public function test_updates_ip_addresses_is_available(): void
    {

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay',
            'private_ip' => "10.0.0.1"
        ]);

        // is_available must be true
        $ip1 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '8.8.8.8',
            'is_available' => false
        ]);

        // no changes
        $ip2 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '9.9.9.9',
            'is_available' => true
        ]);

        // is_available must be false
        $ip3 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '10.10.10.10',
            'is_available' => true
        ]);

        $serverIpMock = $this->createMock(ServerIp::class);
        $serverIpMock->method('getPrivateIp')->willReturn('10.0.0.2');
        $serverIpMock->method('getPublicV4IpAddresses')->willReturn([
            '8.8.8.8',
            '9.9.9.9'
        ]);
        $this->container->set(ServerIp::class, $serverIpMock);

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $this->assertSame('10.0.0.2', $server->getPrivateIp());

        $updatedIp1 = $this->em->getRepository(IpAddress::class)->find($ip1->getId());
        $this->assertNotNull($updatedIp1);
        $this->assertSame('8.8.8.8', $updatedIp1->getIpAddress());
        $this->assertTrue($updatedIp1->getIsAvailable());

        $updatedIp2 = $this->em->getRepository(IpAddress::class)->find($ip2->getId());
        $this->assertNotNull($updatedIp2);
        $this->assertSame('9.9.9.9', $updatedIp2->getIpAddress());
        $this->assertTrue($updatedIp2->getIsAvailable());

        $updatedIp3 = $this->em->getRepository(IpAddress::class)->find($ip3->getId());
        $this->assertNotNull($updatedIp3);
        $this->assertSame('10.10.10.10', $updatedIp3->getIpAddress());
        $this->assertFalse($updatedIp3->getIsAvailable());

    }

    public function test_adds_default_queues(): void
    {
        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $queues = $this->em->getRepository(Queue::class)->findAll();
        $this->assertCount(2, $queues);

    }

}
