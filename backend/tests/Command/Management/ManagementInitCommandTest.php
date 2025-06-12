<?php

namespace App\Tests\Command\Management;

use App\Command\Management\ManagementInitCommand;
use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Server;
use App\Service\Ip\ServerIp;
use App\Service\Management\ManagementService;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ManagementInitCommand::class)]
#[CoversClass(ManagementService::class)]
#[CoversClass(ServerService::class)]
class ManagementInitCommandTest extends KernelTestCase
{

    public function test_creates_server_and_adds_ips(): void
    {

        $serverIpMock = $this->createMock(ServerIp::class);
        $serverIpMock->method('getPublicV4IpAddresses')->willReturn([
            '8.8.8.8',
            '9.9.9.9'
        ]);
        $this->container->set(ServerIp::class, $serverIpMock);

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $servers = $this->em->getRepository(Server::class)->findAll();
        $this->assertCount(1, $servers);
        $server = $servers[0];
        $this->assertSame('hyvor-relay', $server->getHostname());

        $ips = $this->em->getRepository(IpAddress::class)->findBy(['server' => $server]);
        $this->assertCount(2, $ips);

        $this->assertSame('8.8.8.8', $ips[0]->getIpAddress());
        $this->assertTrue($ips[0]->getIsActive());
        $this->assertSame('9.9.9.9', $ips[1]->getIpAddress());
        $this->assertTrue($ips[1]->getIsActive());

    }

    public function test_updates_ip_addresses_is_active(): void
    {

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay'
        ]);

        // is_active must be true
        $ip1 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '8.8.8.8',
            'is_active' => false
        ]);

        // no changes
        $ip2 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '9.9.9.9',
            'is_active' => true
        ]);

        // is_active must be false
        $ip3 = IpAddressFactory::createOne([
            'server' => $server,
            'ip_address' => '10.10.10.10',
            'is_active' => true
        ]);

        $serverIpMock = $this->createMock(ServerIp::class);
        $serverIpMock->method('getPublicV4IpAddresses')->willReturn([
            '8.8.8.8',
            '9.9.9.9'
        ]);
        $this->container->set(ServerIp::class, $serverIpMock);

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $updatedIp1 = $this->em->getRepository(IpAddress::class)->find($ip1->getId());
        $this->assertNotNull($updatedIp1);
        $this->assertSame('8.8.8.8', $updatedIp1->getIpAddress());
        $this->assertTrue($updatedIp1->getIsActive());

        $updatedIp2 = $this->em->getRepository(IpAddress::class)->find($ip2->getId());
        $this->assertNotNull($updatedIp2);
        $this->assertSame('9.9.9.9', $updatedIp2->getIpAddress());
        $this->assertTrue($updatedIp2->getIsActive());

        $updatedIp3 = $this->em->getRepository(IpAddress::class)->find($ip3->getId());
        $this->assertNotNull($updatedIp3);
        $this->assertSame('10.10.10.10', $updatedIp3->getIpAddress());
        $this->assertFalse($updatedIp3->getIsActive());

    }

    public function test_updates_server(): void
    {
        $this->setConfig('apiOn', false);

        $server = ServerFactory::createOne([
            'hostname' => 'hyvor-relay'
        ]);

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

        $updatedServer = $this->em->getRepository(Server::class)->find($server->getId());
        $this->assertNotNull($updatedServer);
        $this->assertSame('hyvor-relay', $updatedServer->getHostname());
        $this->assertFalse($updatedServer->getApiOn());
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