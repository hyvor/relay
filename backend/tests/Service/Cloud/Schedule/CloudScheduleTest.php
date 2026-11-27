<?php

namespace App\Tests\Service\Cloud\Schedule;

use App\Service\Cloud\Schedule\CloudSchedule;
use App\Service\Cloud\MeteredUsage\Message\ReportMeteredUsageMessage;
use Hyvor\Internal\InternalConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Hyvor\Internal\Bundle\Testing\Scheduler\SchedulerTestingTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(CloudSchedule::class)]
class CloudScheduleTest extends TestCase
{

    use SchedulerTestingTrait;

    private function createSchedule(string $deployment): CloudSchedule
    {
        $internalConfig = $this->createStub(InternalConfig::class);
        $internalConfig->method('getDeployment')->willReturn(\Hyvor\Internal\Deployment::from($deployment));

        return new CloudSchedule(
            $internalConfig,
            $this->createStub(LockFactory::class),
            $this->createStub(CacheInterface::class),
        );
    }

    public function test_empty_when_not_cloud(): void
    {
        $schedule = $this->createSchedule('on-prem')->getSchedule();
        $this->assertCount(0, $schedule->getRecurringMessages());
    }

    public function test_cloud_schedule(): void
    {
        $scheduleProvider = $this->createSchedule('cloud');
        $schedule = $scheduleProvider->getSchedule();

        $this->assertCount(1, $schedule->getRecurringMessages());
        $this->assertCount(1, $this->getMessagesOfType($scheduleProvider, ReportMeteredUsageMessage::class));
    }

}
