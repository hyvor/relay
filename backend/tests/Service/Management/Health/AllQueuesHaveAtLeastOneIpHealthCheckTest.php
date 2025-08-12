<?php

namespace App\Tests\Service\Management\Health;

use App\Service\Management\Health\AllQueuesHaveAtLeastOneIpHealthCheck;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AllQueuesHaveAtLeastOneIpHealthCheck::class)]
class AllQueuesHaveAtLeastOneIpHealthCheckTest extends KernelTestCase
{
    private AllQueuesHaveAtLeastOneIpHealthCheck $healthCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthCheck = new AllQueuesHaveAtLeastOneIpHealthCheck(
            $this->em
        );
    }

    public function testCheckReturnsTrueWhenNoQueuesExist(): void
    {
        $result = $this->healthCheck->check();
        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsTrueWhenAllQueuesHaveAtLeastOneActiveEnabledIp(): void
    {
        $server = ServerFactory::createOne();

        $queue1 = QueueFactory::createOne(["name" => "queue_1"]);
        $queue2 = QueueFactory::createOne(["name" => "queue_2"]);

        IpAddressFactory::createOne([
            "queue" => $queue1,
            "server" => $server,
        ]);

        IpAddressFactory::createOne([
            "queue" => $queue2,
            "server" => $server,
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertTrue($result);
        $this->assertEmpty($this->healthCheck->getData());
    }

    public function testCheckReturnsFalseWhenSomeQueuesHaveNoActiveEnabledIps(): void
    {
        $server = ServerFactory::createOne();

        $queueWithIp = QueueFactory::createOne(["name" => "queue_with_ip"]);
        $queueWithoutIp = QueueFactory::createOne(["name" => "queue_without_ip"]);

        IpAddressFactory::createOne([
            "queue" => $queueWithIp,
            "server" => $server,
        ]);

        $this->em->flush();

        $result = $this->healthCheck->check();

        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertIsArray($data["queues_without_ip"]);
        $this->assertContains("queue_without_ip", $data["queues_without_ip"]);
        $this->assertNotContains("queue_with_ip", $data["queues_without_ip"]);
    }

    public function testCheckReturnsCorrectDataForMultipleQueuesWithoutIps(): void
    {
        // Arrange
        $server = ServerFactory::createOne();

        $queueWithIp = QueueFactory::createOne(["name" => "queue_with_ip"]);
        $queueWithoutIp1 = QueueFactory::createOne([
            "name" => "queue_without_ip_1",
        ]);
        $queueWithoutIp2 = QueueFactory::createOne([
            "name" => "queue_without_ip_2",
        ]);

        // Only give IP to first queue
        IpAddressFactory::createOne([
            "queue" => $queueWithIp,
            "server" => $server,
        ]);

        $this->em->flush();

        // Act
        $result = $this->healthCheck->check();

        // Assert
        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertIsArray($data["queues_without_ip"]);
        $this->assertCount(2, $data["queues_without_ip"]);
        $this->assertContains("queue_without_ip_1", $data["queues_without_ip"]);
        $this->assertContains("queue_without_ip_2", $data["queues_without_ip"]);
        $this->assertNotContains("queue_with_ip", $data["queues_without_ip"]);
    }

    public function testCheckHandlesIpAddressWithNullQueue(): void
    {
        // Arrange
        $server = ServerFactory::createOne();
        $queue = QueueFactory::createOne(["name" => "queue_with_null_ip"]);

        // Create IP address with null queue (not associated with any queue)
        IpAddressFactory::createOne([
            "queue" => null,
            "server" => $server,
        ]);

        $this->em->flush();

        // Act
        $result = $this->healthCheck->check();

        // Assert
        $this->assertFalse($result);
        $data = $this->healthCheck->getData();
        $this->assertIsArray($data["queues_without_ip"]);
        $this->assertContains("queue_with_null_ip", $data["queues_without_ip"]);
    }
}
