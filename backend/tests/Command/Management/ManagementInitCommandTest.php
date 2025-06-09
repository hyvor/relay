<?php

namespace App\Tests\Command\Management;

use App\Command\Management\ManagementInitCommand;
use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\Ip\ServerIp;
use App\Service\Management\ManagementService;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
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

}