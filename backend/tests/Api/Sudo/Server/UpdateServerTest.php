<?php

namespace App\Tests\Api\Sudo\Server;

use App\Api\Sudo\Controller\ServerController;
use App\Api\Sudo\Object\ServerObject;
use App\Entity\Server;
use App\Service\Server\ServerService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ServerController::class)]
#[CoversClass(ServerService::class)]
#[CoversClass(ServerObject::class)]
class UpdateServerTest extends WebTestCase
{
    public function test_update_server(): void
    {
        $server = ServerFactory::createOne([
            'hostname' => 'test-server.example.com',
            'api_workers' => 5,
            'email_workers' => 10,
            'webhook_workers' => 3,
        ]);

        $response = $this->adminApi(
            'PATCH',
            '/servers/' . $server->getId(),
            [
                'api_workers' => 8,
                'email_workers' => 15,
                'webhook_workers' => 7,
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->getJson();

        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('hostname', $content);
        $this->assertArrayHasKey('api_workers', $content);
        $this->assertArrayHasKey('email_workers', $content);
        $this->assertArrayHasKey('webhook_workers', $content);

        $this->assertSame($server->getId(), $content['id']);
        $this->assertSame('test-server.example.com', $content['hostname']);
        $this->assertSame(8, $content['api_workers']);
        $this->assertSame(15, $content['email_workers']);
        $this->assertSame(7, $content['webhook_workers']);

        $serverDb = $this->em->getRepository(Server::class)->find($server->getId());
        $this->assertNotNull($serverDb);
        $this->assertSame(8, $serverDb->getApiWorkers());
        $this->assertSame(15, $serverDb->getEmailWorkers());
        $this->assertSame(7, $serverDb->getWebhookWorkers());
    }

    public function test_update_server_partial_update(): void
    {
        $server = ServerFactory::createOne([
            'hostname' => 'test-server.example.com',
            'api_workers' => 5,
            'email_workers' => 10,
            'webhook_workers' => 3,
        ]);

        $response = $this->adminApi(
            'PATCH',
            '/servers/' . $server->getId(),
            [
                'api_workers' => 12,
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();

        $this->assertSame($server->getId(), $content['id']);
        $this->assertSame(12, $content['api_workers']);
        $this->assertSame(10, $content['email_workers']);
        $this->assertSame(3, $content['webhook_workers']);

        $serverDb = $this->em->getRepository(Server::class)->find($server->getId());
        $this->assertNotNull($serverDb);
        $this->assertSame(12, $serverDb->getApiWorkers());
        $this->assertSame(10, $serverDb->getEmailWorkers());
        $this->assertSame(3, $serverDb->getWebhookWorkers());
    }

    public function test_update_server_not_found(): void
    {
        $response = $this->adminApi(
            'PATCH',
            '/servers/999999',
            [
                'api_workers' => 5,
            ]
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_update_server_with_invalid_data(): void
    {
        $server = ServerFactory::createOne([
            'hostname' => 'test-server.example.com',
            'api_workers' => 5,
            'email_workers' => 10,
            'webhook_workers' => 3,
        ]);

        $response = $this->adminApi(
            'PATCH',
            '/servers/' . $server->getId(),
            [
                'api_workers' => -1,
                'email_workers' => 'invalid',
            ]
        );

        $this->assertSame(422, $response->getStatusCode());
    }
} 
