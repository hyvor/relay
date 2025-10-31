<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Management\Health\NoUnreadInfrastructureBouncesHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InfrastructureBounceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NoUnreadInfrastructureBouncesHealthCheck::class)]
class NoUnreadInfrastructureBouncesHealthCheckTest extends KernelTestCase
{
    private NoUnreadInfrastructureBouncesHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthCheck = new NoUnreadInfrastructureBouncesHealthCheck(
            $this->em
        );
    }

    public function testCheckReturnsTrueWhenNoUnreadBounces(): void
    {
        InfrastructureBounceFactory::createMany(5, [
            'is_read' => true,
        ]);

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenUnreadBouncesExist(): void
    {
        InfrastructureBounceFactory::createMany(3, [
            'is_read' => true,
        ]);
        InfrastructureBounceFactory::createMany(2, [
            'is_read' => false,
        ]);

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unread_count', $data);
        $this->assertEquals(2, $data['unread_count']);
    }
}

