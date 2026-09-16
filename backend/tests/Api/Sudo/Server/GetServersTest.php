<?php

namespace App\Tests\Api\Sudo\Server;

use App\Api\Sudo\Controller\ServerController;
use App\Api\Sudo\Object\ServerObject;
use App\Service\Server\ServerService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ServerController::class)]
#[CoversClass(ServerService::class)]
#[CoversClass(ServerObject::class)]
class GetServersTest extends WebTestCase
{

    public function test_get_servers(): void
    {
        // Create test servers
        $server1 = ServerFactory::createOne([
            'hostname' => 'server1.example.com',
        ]);

        $server2 = ServerFactory::createOne([
            'hostname' => 'server2.example.com',
        ]);

        $server3 = ServerFactory::createOne([
            'hostname' => 'server3.example.com',
        ]);

        // Make request to get servers
        $this->sudoApi('GET', '/servers');

        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        /**
         * @var array<array<string, mixed>> $response
         */
        $response = $this->getJson();

        // Assert we have 3 servers, most recently created first
        $this->assertCount(3, $response);

        $this->assertEquals($server3->getId(), $response[0]['id']);
        $this->assertEquals('server3.example.com', $response[0]['hostname']);
        $this->assertEquals($server3->getCreatedAt()->getTimestamp(), $response[0]['created_at']);

        $this->assertEquals($server2->getId(), $response[1]['id']);
        $this->assertEquals('server2.example.com', $response[1]['hostname']);

        $this->assertEquals($server1->getId(), $response[2]['id']);
        $this->assertEquals('server1.example.com', $response[2]['hostname']);
    }

    public function test_get_servers_search(): void
    {
        ServerFactory::createOne(['hostname' => 'mail-primary.example.com']);
        ServerFactory::createOne(['hostname' => 'mail-backup.example.com']);
        ServerFactory::createOne(['hostname' => 'other.example.com']);

        $this->sudoApi('GET', '/servers?search=mail-');
        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertCount(2, $json);
    }
}
