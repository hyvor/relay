<?php

namespace App\Tests\Service\Ip;

use App\Entity\IpAddress;
use App\Service\Instance\InstanceService;
use App\Service\Ip\Ptr;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Ptr::class)]
class PtrTest extends KernelTestCase
{

    public function test_get_ptr_domain(): void
    {
        $ipAddress = new IpAddress();
        $ipAddress->setId(25);
        $this->assertSame('smtp25.relay.hyvor.com', Ptr::getPtrDomain($ipAddress, 'relay.hyvor.com'));
    }

    public function test_validate_success(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'hyvorrelay.com',
        ]);

        $ipAddress = new IpAddress();
        $ipAddress->setId(42);
        $ipAddress->setIpAddress('1.1.1.1');

        $instanceService = $this->getService(InstanceService::class);
        $ptr = new Ptr(
            instanceService: $instanceService,
            gethostbynameFunction: fn($domain) => '1.1.1.1',
            gethostbyaddrFunction: fn($ip) => 'smtp42.hyvorrelay.com',
        );

        $result = $ptr->validate($ipAddress);
        $this->assertTrue($result['forward']);
        $this->assertTrue($result['reverse']);
    }

    public function test_validate_fail(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'hyvorrelay.com',
        ]);

        $ipAddress = new IpAddress();
        $ipAddress->setId(42);
        $ipAddress->setIpAddress('2.2.2.2');

        $instanceService = $this->getService(InstanceService::class);
        $ptr = new Ptr(
            instanceService: $instanceService,
            gethostbynameFunction: fn($domain) => '1.1.1.1',
            gethostbyaddrFunction: fn($ip) => 'smtp99.hyvorrelay.com',
        );

        $result = $ptr->validate($ipAddress);
        $this->assertFalse($result['forward']);
        $this->assertFalse($result['reverse']);
    }

}