<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Management\Health\AllServersCanBeReachedViaPrivateNetwork;
use App\Service\PrivateNetwork\PrivateNetworkApi;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(AllServersCanBeReachedViaPrivateNetwork::class)]
class AllServersCanBeReachedViaPrivateNetworkTest extends KernelTestCase
{
    private AllServersCanBeReachedViaPrivateNetwork $healthCheck;
    private ServerService&MockObject $serverService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->serverService = $this->createMock(ServerService::class);
    }

    /**
     * @param array<MockResponse> $responses
     */
    private function createHealthCheckWithMockedHttpClient(array $responses): void
    {
        $httpClient = new MockHttpClient($responses);
        $this->container->set(HttpClientInterface::class, $httpClient);
        
        $privateNetworkApi = new PrivateNetworkApi($httpClient);
        
        $this->healthCheck = new AllServersCanBeReachedViaPrivateNetwork(
            $privateNetworkApi,
            $this->serverService
        );
    }

    public function testCheckReturnsTrueWhenNoServersExist(): void
    {
        $this->createHealthCheckWithMockedHttpClient([]);
        
        $this->serverService->expects($this->once())
            ->method('getServers')
            ->willReturn([]);

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

        $responses = [
            new MockResponse('[]', ['http_code' => 200]),
        ];
        
        $this->createHealthCheckWithMockedHttpClient($responses);

        $this->serverService->expects($this->once())
            ->method('getServers')
            ->willReturn([$currentServer->_real()]);

        $this->serverService->expects($this->once())
            ->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());

        $this->assertSame('http://10.0.0.1/api/local/ping', $responses[0]->getRequestUrl());
        $this->assertSame('GET', $responses[0]->getRequestMethod());
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

        $responses = [
            new MockResponse('[]', ['http_code' => 200]), // current server ping
            new MockResponse('[]', ['http_code' => 200]), // server1 ping
            new MockResponse('[]', ['http_code' => 200]), // server2 ping
        ];
        
        $this->createHealthCheckWithMockedHttpClient($responses);

        $this->serverService->method('getServers')
            ->willReturn([$currentServer->_real(), $server1->_real(), $server2->_real()]);

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $result = $this->healthCheck->check();
        
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());

        $this->assertSame('http://10.0.0.1/api/local/ping', $responses[0]->getRequestUrl());
        $this->assertSame('GET', $responses[0]->getRequestMethod());
        $this->assertSame('http://10.0.0.2/api/local/ping', $responses[1]->getRequestUrl());
        $this->assertSame('GET', $responses[1]->getRequestMethod());
        $this->assertSame('http://10.0.0.3/api/local/ping', $responses[2]->getRequestUrl());
        $this->assertSame('GET', $responses[2]->getRequestMethod());
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

        $responses = [
            new MockResponse('[]', ['http_code' => 200]), // current server
            new MockResponse('[]', ['http_code' => 200]), // reachable server
            new MockResponse('Connection failed', ['http_code' => 500]), // unreachable server
        ];
        
        $this->createHealthCheckWithMockedHttpClient($responses);

        $this->serverService->method('getServers')
            ->willReturn([$currentServer->_real(), $reachableServer->_real(), $unreachableServer->_real()]);

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $result = $this->healthCheck->check();
        
        $this->assertFalse($result);
        
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unreachable_servers', $data);
        $this->assertArrayHasKey('checking_server', $data);
        $this->assertEquals(['unreachable-server.example.com'], $data['unreachable_servers']);
        $this->assertEquals('current-server.example.com', $data['checking_server']);

        $this->assertSame('http://10.0.0.1/api/local/ping', $responses[0]->getRequestUrl());
        $this->assertSame('GET', $responses[0]->getRequestMethod());
        $this->assertSame('http://10.0.0.2/api/local/ping', $responses[1]->getRequestUrl());
        $this->assertSame('GET', $responses[1]->getRequestMethod());
        $this->assertSame('http://10.0.0.3/api/local/ping', $responses[2]->getRequestUrl());
        $this->assertSame('GET', $responses[2]->getRequestMethod());
    }

    public function testCheckReturnsFalseWithServersWithoutPrivateIp(): void
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

        $responses = [
            new MockResponse('[]', ['http_code' => 200]), // current server
            new MockResponse('[]', ['http_code' => 200]), // server with IP
        ];
        
        $this->createHealthCheckWithMockedHttpClient($responses);

        $this->serverService->method('getServers')
            ->willReturn([$currentServer->_real(), $serverWithoutIp->_real(), $serverWithIp->_real()]);

        $this->serverService->method('getServerByCurrentHostname')
            ->willReturn($currentServer->_real());

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unreachable_servers', $data);
        $this->assertArrayHasKey('checking_server', $data);
        $this->assertEquals(['server-without-ip.example.com'], $data['unreachable_servers']);
        $this->assertEquals('current-server.example.com', $data['checking_server']);

        $this->assertSame('http://10.0.0.1/api/local/ping', $responses[0]->getRequestUrl());
        $this->assertSame('GET', $responses[0]->getRequestMethod());
        $this->assertSame('http://10.0.0.2/api/local/ping', $responses[1]->getRequestUrl());
        $this->assertSame('GET', $responses[1]->getRequestMethod());
    }

}
