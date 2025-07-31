<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Management\Health\AllIpsAreInSpfRecordHealthCheck;
use App\Tests\Case\KernelTestCase;

use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\IpAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use SPFLib\Check\Environment;
use SPFLib\Check\Result;
use SPFLib\Checker;
use SPFLib\DNS\Resolver;


#[CoversClass(AllIpsAreInSpfRecordHealthCheck::class)]
class AllIpsAreInSpfRecordHealthCheckTest extends KernelTestCase
{
    private AllIpsAreInSpfRecordHealthCheck $healthCheck;
    private Resolver&MockObject $dnsResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsResolver = $this->createMock(Resolver::class);

        $this->healthCheck = new AllIpsAreInSpfRecordHealthCheck(
            $this->container->get(InstanceService::class),
            $this->container->get(IpAddressService::class),
            $this->dnsResolver
        );
    }

    public function testCheckReturnsTrueWhenIpsAreInSpfRecord(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'example.com',
        ]);

        $ip_address1 = IpAddressFactory::createOne();
        $ip_address2 = IpAddressFactory::createOne();

        $this->dnsResolver->method('getTXTRecords')
            ->willReturn(['v=spf1 ip4:' . $ip_address1->getIpAddress() . ' ip4:' . $ip_address2->getIpAddress() . ' -all']);

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenIpsAreNotInSpfRecord(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'example.com',
        ]);

        $ip_address1 = IpAddressFactory::createOne();
        $ip_address2 = IpAddressFactory::createOne();

        $this->dnsResolver->method('getTXTRecords')
            ->willReturn(['v=spf1 ip4:' . $ip_address1->getIpAddress() . ' -all']);

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('invalid_ips', $data);
        $this->assertArrayHasKey('domain', $data);
        $this->assertSame([$ip_address2->getIpAddress()], $data['invalid_ips']);
        $this->assertEquals('example.com', $data['domain']);
    }

    public function testCheckReturnsFalseWhenInvalidIp(): void
    {
        $instance = InstanceFactory::createOne([
            'domain' => 'example.com',
        ]);

        $ip_address1 = IpAddressFactory::createOne(
            [
                'ip_address' => 'invalid-ip-address'
            ]
        );
        $ip_address2 = IpAddressFactory::createOne();

        $this->dnsResolver->method('getTXTRecords')
            ->willReturn(['v=spf1 ip4:' . $ip_address2->getIpAddress() . ' -all']);

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('invalid_ips', $data);
        $this->assertArrayHasKey('domain', $data);
        $this->assertSame([$ip_address1->getIpAddress()], $data['invalid_ips']);
        $this->assertEquals('example.com', $data['domain']);
    }
} 
