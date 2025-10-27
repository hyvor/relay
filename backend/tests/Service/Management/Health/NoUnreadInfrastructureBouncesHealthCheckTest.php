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

    public function testCheckReturnsTrueWhenNoBouncesExist(): void
    {
        $result = $this->healthCheck->check();
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsTrueWhenAllBouncesAreRead(): void
    {
        InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenUnreadBouncesExist(): void
    {
        InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'smtp_code' => 550,
            'smtp_enhanced_code' => '5.1.1',
            'smtp_message' => 'User unknown',
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unread_count', $data);
        $this->assertEquals(1, $data['unread_count']);
        $this->assertArrayHasKey('unread_bounces', $data);
        $this->assertIsArray($data['unread_bounces']);
        $this->assertCount(1, $data['unread_bounces']);
    }

    public function testCheckReturnsFalseWithMixedReadAndUnreadBounces(): void
    {
        InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        InfrastructureBounceFactory::createOne([
            'is_read' => true,
        ]);

        InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'smtp_code' => 550,
            'smtp_enhanced_code' => '5.1.1',
            'smtp_message' => 'User unknown',
        ]);

        InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'smtp_code' => 554,
            'smtp_enhanced_code' => '5.7.1',
            'smtp_message' => 'Relay access denied',
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertArrayHasKey('unread_count', $data);
        $this->assertEquals(2, $data['unread_count']);
        $this->assertArrayHasKey('unread_bounces', $data);
        $this->assertIsArray($data['unread_bounces']);
        $this->assertCount(2, $data['unread_bounces']);
    }

    public function testCheckReturnsCorrectDataStructure(): void
    {
        $bounce = InfrastructureBounceFactory::createOne([
            'is_read' => false,
            'smtp_code' => 550,
            'smtp_enhanced_code' => '5.1.1',
            'smtp_message' => 'User unknown',
            'send_recipient_id' => 123,
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        
        $this->assertIsArray($data['unread_bounces']);
        $this->assertCount(1, $data['unread_bounces']);
        
        $bounceData = $data['unread_bounces'][0];
        $this->assertArrayHasKey('id', $bounceData);
        $this->assertArrayHasKey('smtp_code', $bounceData);
        $this->assertArrayHasKey('smtp_enhanced_code', $bounceData);
        $this->assertArrayHasKey('smtp_message', $bounceData);
        $this->assertArrayHasKey('send_recipient_id', $bounceData);
        $this->assertArrayHasKey('created_at', $bounceData);
        
        $this->assertEquals(550, $bounceData['smtp_code']);
        $this->assertEquals('5.1.1', $bounceData['smtp_enhanced_code']);
        $this->assertEquals('User unknown', $bounceData['smtp_message']);
        $this->assertEquals(123, $bounceData['send_recipient_id']);
    }
}

