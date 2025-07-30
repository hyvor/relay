<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\Server;
use App\Service\Management\Health\AllServersCanBeReachedViaPrivateNetwork;
use App\Service\PrivateNetwork\Exception\PrivateNetworkCallException;
use App\Service\PrivateNetwork\PrivateNetworkApi;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(AllServersCanBeReachedViaPrivateNetwork::class)]
class AllServersCanBeReachedViaPrivateNetworkTest extends KernelTestCase
{
    private AllServersCanBeReachedViaPrivateNetwork $healthCheck;
    private PrivateNetworkApi&MockObject $privateNetworkApi;
    private ServerService&MockObject $serverService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->privateNetworkApi = $this->createMock(PrivateNetworkApi::class);
        $this->serverService = $this->createMock(ServerService::class);
        
        $this->healthCheck = new AllServersCanBeReachedViaPrivateNetwork(
            $this->em,
            $this->privateNetworkApi,
            $this->serverService
        );
    }

    public function testCheckReturnsTrueWhenNoServersExist(): void
    {
        $this->serverService->expects($this->once())
            ->method('getServerByCurrentHostname')
            ->willReturn(null);

        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsTrueWhenOnlyCurrentServerExists(): void
    {
        $currentServer = ServerFactory::createOne([
            'hostname' => 'current-server.example.com',
            'private_ip' => '10.0.0.1'
        ]);
        
        $this->em->flush();

        $this->serverService->expects($this->once())
            ->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsTrueWhenAllServersAreReachable(): void
    {
        $currentServer = ServerFactory::createOne([
            'hostname' => 'current-server.example.com',
            'private_ip' => '10.0.0.1'
        ]);
        
        $server1 = ServerFactory::createOne([
            'hostname' => 'server1.example.com',
            'private_ip' => '10.0.0.2'
        ]);
        
        $server2 = ServerFactory::createOne([
            'hostname' => 'server2.example.com',
            'private_ip' => '10.0.0.3'
        ]);
        
        $this->em->flush();

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $this->privateNetworkApi
            ->method('pingServer')
            ->willReturnCallback(function ($server) use ($currentServer) {
                $this->assertInstanceOf(Server::class, $server);
                $this->assertNotEquals($currentServer->getHostname(), $server->getHostname());
                $this->assertNotNull($server->getPrivateIp());
            });

        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenSomeServersAreUnreachable(): void
    {
        $currentServer = ServerFactory::createOne([
            'hostname' => 'current-server.example.com',
            'private_ip' => '10.0.0.1'
        ]);
        
        $reachableServer = ServerFactory::createOne([
            'hostname' => 'reachable-server.example.com',
            'private_ip' => '10.0.0.2'
        ]);
        
        $unreachableServer = ServerFactory::createOne([
            'hostname' => 'unreachable-server.example.com',
            'private_ip' => '10.0.0.3'
        ]);
        
        $this->em->flush();

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $pingCount = 0;
        $this->privateNetworkApi->method('pingServer')
            ->willReturnCallback(function ($server) use ($unreachableServer, &$pingCount) {
                $pingCount++;
                $this->assertInstanceOf(Server::class, $server);
                // Simulate that the unreachable server throws an exception
                if ($server->getHostname() === $unreachableServer->getHostname()) {
                    throw new PrivateNetworkCallException('Connection failed');
                }
            });

        $result = $this->healthCheck->check();
        
        $this->assertFalse($result);
        
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unreachable_servers', $data);
        $this->assertArrayHasKey('checking_server', $data);
        $this->assertEquals(['unreachable-server.example.com'], $data['unreachable_servers']);
        $this->assertEquals('current-server.example.com', $data['checking_server']);
    }

    public function testCheckSkipsServersWithoutPrivateIp(): void
    {
        $currentServer = ServerFactory::createOne([
            'hostname' => 'current-server.example.com',
            'private_ip' => '10.0.0.1'
        ]);
        
        $serverWithoutIp = ServerFactory::createOne([
            'hostname' => 'server-without-ip.example.com',
            'private_ip' => null
        ]);
        
        $serverWithIp = ServerFactory::createOne([
            'hostname' => 'server-with-ip.example.com',
            'private_ip' => '10.0.0.2'
        ]);
        
        $this->em->flush();

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        // Should only ping the server with IP, not the one without
        $this->privateNetworkApi->method('pingServer')
            ->willReturnCallback(function ($server) use ($serverWithoutIp) {
                $this->assertInstanceOf(Server::class, $server);
                $this->assertNotEquals($serverWithoutIp->getHostname(), $server->getHostname());
                $this->assertNotNull($server->getPrivateIp());
            });
        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

}
