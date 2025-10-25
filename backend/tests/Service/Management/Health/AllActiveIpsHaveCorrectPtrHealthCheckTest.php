<?php

namespace App\Tests\Service\Management\Health;

use App\Entity\IpAddress;
use App\Service\Ip\Ptr;
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
    private Ptr&MockObject $ptr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ptr = $this->createMock(Ptr::class);

        $this->container->set(Ptr::class, $this->ptr);
        $ipAddressService = $this->getService(IpAddressService::class);

        $this->healthCheck = new AllActiveIpsHaveCorrectPtrHealthCheck(
            $this->em,
            $ipAddressService
        );
    }

    public function testCheckReturnsTrueWhenNoActiveIpsExist(): void
    {
        $this->ptr->expects($this->never())
            ->method('validate');

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

        $this->ptr->method('validate')
            ->willReturn([
                'forward' => true,
                'reverse' => true,
            ]);

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

        $this->ptr->method('validate')
            ->willReturn([
                'forward' => false,
                'reverse' => false,
            ]);

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $this->assertNotEmpty($this->healthCheck->getData());
        $this->assertArrayHasKey('invalid_ptrs', $this->healthCheck->getData());
        $this->assertIsArray($this->healthCheck->getData()['invalid_ptrs']);
        $this->assertCount(2, $this->healthCheck->getData()['invalid_ptrs']);
    }
} 
