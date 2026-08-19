<?php

namespace App\Tests\Service\Ip;

use App\Entity\Type\WarmupStatus;
use App\Service\Ip\IpSelector;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use App\Tests\Factory\WarmupScheduleFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpSelector::class)]
class IpSelectorTest extends KernelTestCase
{
    public function test_get_ip_falls_back_to_warming_with_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $warmingIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $warmingIp,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('2026-06-01'),
            'schedule' => array_fill(0, 30, 1000),
            'max_today' => 1000,
            'sent_today' => 500,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $ip = $selector->selectForQueue($queue);

        $this->assertNotNull($ip);
        $this->assertSame($warmingIp->getId(), $ip->getId());
    }

    public function test_get_ip_returns_null_when_all_at_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $ip = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('2026-06-01'),
            'schedule' => array_fill(0, 30, 1000),
            'max_today' => 1000,
            'sent_today' => 1000,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $result = $selector->selectForQueue($queue);

        $this->assertNull($result);
    }

    public function test_get_ip_returns_null_when_no_ips(): void
    {
        $queue = QueueFactory::createOne();

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $ip = $selector->selectForQueue($queue);

        $this->assertNull($ip);
    }

    public function test_get_ip_skips_warming_ip_without_enough_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $fullIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $fullIp,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('2026-06-01'),
            'schedule' => array_fill(0, 30, 1000),
            'max_today' => 1000,
            'sent_today' => 1000,
        ]);

        $availableIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $availableIp,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('2026-06-01'),
            'schedule' => array_fill(0, 30, 1000),
            'max_today' => 1000,
            'sent_today' => 500,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);

        $ip = $selector->selectForQueue($queue, 10);

        $this->assertNotNull($ip);
        $this->assertSame($availableIp->getId(), $ip->getId());
    }

    public function test_warmed_ip_returned_without_capacity_check(): void
    {
        $queue = QueueFactory::createOne();

        $ip = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMED,
            'started_date' => new \DateTimeImmutable('2026-05-01'),
            'schedule' => array_fill(0, 30, 100),
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $result = $selector->selectForQueue($queue);

        $this->assertNotNull($result);
        $this->assertSame($ip->getId(), $result->getId());
    }

    public function test_ip_without_warmup_schedule_is_returned(): void
    {
        $queue = QueueFactory::createOne();

        $ip = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $result = $selector->selectForQueue($queue);

        $this->assertNotNull($result);
        $this->assertSame($ip->getId(), $result->getId());
    }
}
