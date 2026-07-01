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
class IpAddressServiceTest extends KernelTestCase
{

    public function test_get_ip_prefers_non_warming_ips(): void
    {
        $queue = QueueFactory::createOne();

        $warmingIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $warmingIp,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
        ]);

        $warmedIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);

        for ($i = 0; $i < 20; $i++) {
            $ip = $selector->selectForQueue($queue->_real());
            $this->assertNotNull($ip);
            $this->assertSame($warmedIp->getId(), $ip->getId());
        }
    }

    public function test_get_ip_falls_back_to_warming_with_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $warmingIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $warmingIp,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 500,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $ip = $selector->selectForQueue($queue->_real());

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
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 1000,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $result = $selector->selectForQueue($queue->_real());

        $this->assertNull($result);
    }

    public function test_get_ip_returns_null_when_no_ips(): void
    {
        $queue = QueueFactory::createOne();

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $ip = $selector->selectForQueue($queue->_real());

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
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 1000,
        ]);

        $availableIp = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $availableIp,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 500,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);

        $ip = $selector->selectForQueue($queue->_real(), 10);

        $this->assertNotNull($ip);
        $this->assertSame($availableIp->getId(), $ip->getId());
    }

    public function test_warming_ip_without_schedule_not_considered_warming(): void
    {
        $queue = QueueFactory::createOne();

        $ip = IpAddressFactory::createOne([
            'queue' => $queue,
        ]);

        WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => null,
            'warmup_schedule' => null,
        ]);

        /** @var IpSelector $selector */
        $selector = $this->container->get(IpSelector::class);
        $result = $selector->selectForQueue($queue->_real());

        $this->assertNotNull($result);
        $this->assertSame($ip->getId(), $result->getId());
    }

}
