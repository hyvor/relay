<?php

namespace Api\Admin\Server;

use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ServerFactory;

class GetServersTest extends WebTestCase
{

    public function test_get_servers(): void
    {
        // Create test servers
        $server1 = ServerFactory::createOne([
            'hostname' => 'server1.example.com',
            'api_workers' => 5,
            'email_workers' => 3,
            'webhook_workers' => 2,
        ]);
        
        $server2 = ServerFactory::createOne([
            'hostname' => 'server2.example.com',
            'api_workers' => 5,
            'email_workers' => 3,
            'webhook_workers' => 2,
        ]);
        
        $server3 = ServerFactory::createOne([
            'hostname' => 'server3.example.com',
            'api_workers' => 5,
            'email_workers' => 3,
            'webhook_workers' => 2,
        ]);

        // Make request to get servers
        $response = $this->adminApi('GET', '/servers');


        // Assert response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        /**
         * @var array<array<string, mixed>> $response
         */
        $response = $this->getJson();

        // Assert we have 3 servers
        $this->assertCount(3, $response);

        // Assert server 1 data
        $this->assertEquals($server1->getId(), $response[0]['id']);
        $this->assertEquals('server1.example.com', $response[0]['hostname']);
        $this->assertEquals($server1->getCreatedAt()->getTimestamp(), $response[0]['created_at']);
        $this->assertEquals(5, $response[0]['api_workers']);
        $this->assertEquals(3, $response[0]['email_workers']);
        $this->assertEquals(2, $response[0]['webhook_workers']);


        // Assert server 2 data
        $this->assertEquals($server2->getId(), $response[1]['id']);
        $this->assertEquals('server2.example.com', $response[1]['hostname']);
        $this->assertEquals($server2->getCreatedAt()->getTimestamp(), $response[1]['created_at']);
        $this->assertEquals(5, $response[1]['api_workers']);
        $this->assertEquals(3, $response[1]['email_workers']);
        $this->assertEquals(2, $response[1]['webhook_workers']);

        // Assert server 3 data
        $this->assertEquals($server3->getId(), $response[2]['id']);
        $this->assertEquals('server3.example.com', $response[2]['hostname']);
        $this->assertEquals($server3->getCreatedAt()->getTimestamp(), $response[2]['created_at']);
         $this->assertEquals(5, $response[2]['api_workers']);
        $this->assertEquals(3, $response[2]['email_workers']);
        $this->assertEquals(2, $response[2]['webhook_workers']);
    }
}
