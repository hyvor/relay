<?php

namespace App\Tests\Api\Sudo\IpAddress;

use App\Api\Sudo\Controller\IpAddressController;
use App\Service\Ip\IpAddressService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpAddressController::class)]
#[CoversClass(IpAddressService::class)]
class GetIpAddressesTest extends WebTestCase
{

    public function test_get_ip_addresses(): void
    {
        $instance = InstanceFactory::createOne();
        $ip1 = IpAddressFactory::createOne();
        $ip2 = IpAddressFactory::createOne();

        $this->sudoApi('GET', '/ip-addresses');

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertCount(2, $json);
    }

}