<?php

namespace App\Tests\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Type\WarmupStatus;
use App\Service\Ip\IpAddressService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IpAddressService::class)]
class IpAddressServiceTest extends KernelTestCase
{

    public function test_get_ip_prefers_non_warming_ips(): void
    {
        $queue = QueueFactory::createOne();

        $warmingIp = IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
        ]);

        $warmedIp = IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMED,
        ]);

        $service = $this->container->get(IpAddressService::class);

        for ($i = 0; $i < 20; $i++) {
            $ip = $service->getIpForQueue($queue->_real());
            $this->assertNotNull($ip);
            $this->assertSame($warmedIp->getId(), $ip->getId());
        }
    }

    public function test_get_ip_falls_back_to_warming_with_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $warmingIp = IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 500,
        ]);

        $service = $this->container->get(IpAddressService::class);
        $ip = $service->getIpForQueue($queue->_real());

        $this->assertNotNull($ip);
        $this->assertSame($warmingIp->getId(), $ip->getId());
    }

    public function test_get_ip_returns_null_when_all_at_capacity(): void
    {
        $queue = QueueFactory::createOne();

        IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 1000,
        ]);

        $service = $this->container->get(IpAddressService::class);
        $ip = $service->getIpForQueue($queue->_real());

        $this->assertNull($ip);
    }

    public function test_get_ip_returns_null_when_no_ips(): void
    {
        $queue = QueueFactory::createOne();
        $service = $this->container->get(IpAddressService::class);
        $ip = $service->getIpForQueue($queue->_real());

        $this->assertNull($ip);
    }

    public function test_get_ip_skips_warming_ip_without_enough_capacity(): void
    {
        $queue = QueueFactory::createOne();

        $fullIp = IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 1000,
        ]);

        $availableIp = IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('2026-06-01'),
            'warmup_schedule' => array_fill(0, 30, 1000),
            'warmup_max_today' => 1000,
            'warmup_sent_today' => 500,
        ]);

        $service = $this->container->get(IpAddressService::class);

        // With recipientCount=10, full IP has 1000+10=1010 > 1000, so skipped
        // available IP has 500+10=510 <= 1000, so selected
        $ip = $service->getIpForQueue($queue->_real(), 10);

        $this->assertNotNull($ip);
        $this->assertSame($availableIp->getId(), $ip->getId());
    }

    public function test_warming_ip_without_schedule_not_considered_warming(): void
    {
        $queue = QueueFactory::createOne();

        // Status is WARMING but no schedule, so isWarmingUp() returns false
        IpAddressFactory::createOne([
            'queue' => $queue,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => null,
            'warmup_schedule' => null,
        ]);

        $service = $this->container->get(IpAddressService::class);
        $ip = $service->getIpForQueue($queue->_real());

        $this->assertNotNull($ip);
        $this->assertFalse($ip->isWarmingUp());
    }

}
