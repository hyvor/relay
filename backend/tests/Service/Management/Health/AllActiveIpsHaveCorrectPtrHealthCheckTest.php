<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Management\Health\AllActiveIpsHaveCorrectPtrHealthCheck;
use App\Service\Ip\IpAddressService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(AllActiveIpsHaveCorrectPtrHealthCheck::class)]
#[CoversClass(IpAddressService::class)]
class AllActiveIpsHaveCorrectPtrHealthCheckTest extends KernelTestCase
{
    private AllActiveIpsHaveCorrectPtrHealthCheck $healthCheck;
    private IpAddressService&MockObject $ipAddressService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ipAddressService = $this->createMock(IpAddressService::class);

        $this->healthCheck = new AllActiveIpsHaveCorrectPtrHealthCheck(
            $this->em,
            $this->ipAddressService
        );
    }

    public function testCheckReturnsTrueWhenNoActiveIpsExist(): void
    {
        $this->ipAddressService->expects($this->never())
            ->method('updateIpPtrValidity');


        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsTrueWhenAllActiveIpsHaveCorrectPtr(): void
    {
        $ip = IpAddressFactory::createOne([
            'queue' => QueueFactory::new(),
            'is_ptr_forward_valid' => true,
            'is_ptr_reverse_valid' => true,
        ]);

        $this->ipAddressService->method('updateIpPtrValidity')
            ->willReturnCallback(function ($ip) {
                $this->assertInstanceOf(IpAddress::class, $ip);
                $ip->setIsPtrForwardValid(true);
                $ip->setIsPtrReverseValid(true);
            });

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenSomeActiveIpsHaveIncorrectPtr(): void
    {
        $ip1 = IpAddressFactory::createOne([
            'queue' => QueueFactory::new(),
            'is_ptr_forward_valid' => false,
            'is_ptr_reverse_valid' => true,
        ]);

        $ip2 = IpAddressFactory::createOne([
            'queue' => QueueFactory::new(),
            'is_ptr_forward_valid' => true,
            'is_ptr_reverse_valid' => false,
        ]);

        $this->ipAddressService->method('updateIpPtrValidity')
            ->willReturnCallback(function ($ip) {
                $this->assertInstanceOf(IpAddress::class, $ip);
                $ip->setIsPtrForwardValid(false);
                $ip->setIsPtrReverseValid(false);
            });

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $this->assertNotEmpty($this->healthCheck->getData());
        $this->assertArrayHasKey('invalid_ptrs', $this->healthCheck->getData());
        $this->assertIsArray($this->healthCheck->getData()['invalid_ptrs']);
        $this->assertCount(2, $this->healthCheck->getData()['invalid_ptrs']);
    }
} 
