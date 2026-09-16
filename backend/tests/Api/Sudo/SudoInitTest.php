<?php

namespace App\Tests\Api\Sudo;

use App\Api\Sudo\Controller\SudoController;
use App\Api\Sudo\Object\InstanceObject;
use App\Service\Blacklist\IpBlacklist;
use App\Service\Blacklist\IpBlacklists;
use App\Service\Instance\InstanceService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SudoController::class)]
#[CoversClass(IpBlacklists::class)]
#[CoversClass(IpBlacklist::class)]
#[CoversClass(InstanceObject::class)]
#[CoversClass(InstanceService::class)]
class SudoInitTest extends WebTestCase
{

    public function test_inits_sudo(): void
    {
        $this->sudoApi('POST', '/init');
        $this->assertResponseIsSuccessful();
        $json = $this->getJson();
        $this->assertArrayHasKey('config', $json);
        $this->assertArrayHasKey('instance', $json);
        $this->assertArrayHasKey('servers', $json);
        $this->assertArrayHasKey('ip_addresses', $json);
    }

    public function test_inits_sudo_with_servers_and_ip_addresses(): void
    {
        $server = ServerFactory::createOne(['hostname' => 'server.example.com']);
        $ip = IpAddressFactory::createOne(['server' => $server, 'ip_address' => '1.1.1.1']);

        $this->em->clear();

        $this->sudoApi('POST', '/init');
        $this->assertResponseIsSuccessful();
        $json = $this->getJson();

        $this->assertCount(1, $json['servers']);
        $this->assertEquals($server->getId(), $json['servers'][0]['id']);

        $this->assertCount(1, $json['ip_addresses']);
        $this->assertEquals($ip->getId(), $json['ip_addresses'][0]['id']);
        $this->assertEquals('1.1.1.1', $json['ip_addresses'][0]['ip_address']);
    }

    public function test_fails_when_not_sudo(): void
    {
        $this->sudoApi('POST', '/init', createSudoUser: false);
        $this->assertResponseStatusCodeSame(403);
    }
}
