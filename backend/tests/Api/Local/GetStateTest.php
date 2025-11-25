<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Api\Local\LocalAuthorizationListener;
use App\Service\Management\GoState\GoState;
use App\Service\Management\GoState\GoStateDnsRecordsService;
use App\Service\Management\GoState\GoStateFactory;
use App\Service\Management\GoState\GoStateIp;
use App\Service\Server\ServerService;
use App\Service\Tls\TlsCertificateService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DnsRecordFactory;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
#[CoversClass(GoStateFactory::class)]
#[CoversClass(ServerService::class)]
#[CoversClass(GoState::class)]
#[CoversClass(GoStateIp::class)]
#[CoversClass(LocalAuthorizationListener::class)]
#[CoversClass(GoStateDnsRecordsService::class)]
#[CoversClass(TlsCertificateService::class)]
class GetStateTest extends WebTestCase
{

    public function test_cannot_call_from_non_localhost_ip(): void
    {
        $response = $this->localApi(
            "GET",
            "/state",
            server: [
                'REMOTE_ADDR' => '8.8.8.8'
            ]
        );

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            'Only requests from localhost are allowed. Current IP is: 8.8.8.8',
            $this->getJson()["message"]
        );
    }

    public function test_gets_state(): void
    {
        $server = ServerFactory::createOne(['hostname' => 'hyvor-relay']);
        $ipEntity = IpAddressFactory::createOne([
            'server' => $server,
            'queue' => QueueFactory::new(),
        ]);

        // no queue
        IpAddressFactory::createOne([
            'server' => $server,
            'queue' => null,
        ]);

        DnsRecordFactory::createOne();

        $this->localApi(
            'GET',
            '/state'
        );
        $this->assertResponseIsSuccessful();

        $json = $this->getJson();

        $this->assertSame('mail.hyvor-relay.com', $json['instanceDomain']);
        $this->assertSame('hyvor-relay', $json['hostname']);
        $this->assertSame('test', $json['env']);

        $ips = $json['ips'];
        $this->assertIsArray($ips);
        $this->assertCount(1, $ips);

        $ip = $ips[0];
        $this->assertIsArray($ip);
        $this->assertSame($ipEntity->getId(), $ip['id']);
        $this->assertSame('smtp' . $ipEntity->getId() . '.mail.hyvor-relay.com', $ip['ptr']);
    }

    public function test_fails_when_server_not_initialized(): void
    {
        $this->localApi(
            'GET',
            '/state'
        );

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            "Server not yet initialized",
            $this->getJson()["message"]
        );
    }

}