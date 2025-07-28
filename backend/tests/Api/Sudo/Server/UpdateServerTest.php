<?php

namespace App\Tests\Api\Sudo\Server;

use App\Api\Sudo\Controller\ServerController;
use App\Api\Sudo\Object\ServerObject;
use App\Entity\Server;
use App\Service\PrivateNetwork\PrivateNetworkApi;
use App\Service\Server\ServerService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ServerController::class)]
#[CoversClass(ServerService::class)]
#[CoversClass(ServerObject::class)]
#[CoversClass(PrivateNetworkApi::class)]
class UpdateServerTest extends WebTestCase
{

    public function test_update_server_api_workers(): void
    {

        $mockResponse = new JsonMockResponse();
        $this->container->set(HttpClientInterface::class, new MockHttpClient($mockResponse));

        // Create test server
        $server = ServerFactory::createOne([
            'hostname' => 'test-server.example.com',
            'api_workers' => 5,
            'email_workers' => 3,
            'webhook_workers' => 2,
            'private_ip' => '10.0.0.1'
        ]);

        // Make request to update api workers
        $this->sudoApi('PATCH', '/servers/' . $server->getId(), [
            'api_workers' => 10,
            'email_workers' => 4,
            'webhook_workers' => 1
        ]);

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        /**
         * @var array<string, mixed> $response
         */
        $response = $this->getJson();

        // Assert server data
        $this->assertEquals($server->getId(), $response['id']);
        $this->assertEquals('test-server.example.com', $response['hostname']);
        $this->assertEquals(10, $response['api_workers']);
        $this->assertEquals(4, $response['email_workers']); // unchanged
        $this->assertEquals(1, $response['webhook_workers']); // unchanged

        $this->assertSame('http://10.0.0.1/api/local/state/update', $mockResponse->getRequestUrl());
        $this->assertSame('POST', $mockResponse->getRequestMethod());
    }

    public function test_does_not_update_if_state_update_api_call_fails(): void
    {
        $mockResponse = new MockResponse(info: ['error' => 'host unreachable']);
        $this->container->set(HttpClientInterface::class, new MockHttpClient($mockResponse));

        // Create test server
        $server = ServerFactory::createOne([
            'api_workers' => 4,
            'private_ip' => '10.0.0.1'
        ]);

        $this->sudoApi('PATCH', '/servers/' . $server->getId(), [
            'api_workers' => 10,
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame("Failed to call private network API: host unreachable", $this->getJson()['message']);

        $servers = $this->em->getRepository(Server::class)->findAll();
        $this->assertCount(1, $servers);
        $this->assertSame(4, $servers[0]->getApiWorkers());
    }

    public function test_update_server_with_nonexistent_id(): void
    {
        $this->sudoApi('PATCH', '/servers/99999', [
            'api_workers' => 10,
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        /**
         * @var array<string, mixed> $response
         */
        $response = $this->getJson();

        // Assert error message
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Server with ID 99999 does not exist.', $response['message']);
    }

}
