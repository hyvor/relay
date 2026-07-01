<?php

namespace App\Tests\Service\Ip\MessageHandler;

use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;
use App\Service\App\MessageTransport;
use App\Service\Ip\Message\ResetIpWarmupMessage;
use App\Service\Ip\MessageHandler\ResetIpWarmupMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\WarmupScheduleFactory;
use PHPUnit\Framework\Attributes\CoversClass;

use function Zenstruck\Foundry\Persistence\refresh;

#[CoversClass(ResetIpWarmupMessageHandler::class)]
class ResetIpWarmupMessageHandlerTest extends KernelTestCase
{

    public function test_resets_sent_today_and_updates_max(): void
    {
        $schedule = array_fill(0, 30, 100);
        $schedule[1] = 200;
        $schedule[2] = 300;

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')),
            'warmup_schedule' => $schedule,
            'warmup_sent_today' => 50,
            'warmup_max_today' => 100,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(0, $warmup->getWarmupSentToday());
        $this->assertSame(200, $warmup->getWarmupMaxToday());
        $this->assertSame(WarmupStatus::WARMING, $warmup->getWarmupStatus());
    }

    public function test_auto_transitions_to_warmed_after_day_30(): void
    {
        $schedule = array_fill(0, 30, 100);

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('-31 days', new \DateTimeZone('UTC')),
            'warmup_schedule' => $schedule,
            'warmup_sent_today' => 50,
            'warmup_max_today' => 100,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(WarmupStatus::WARMED, $warmup->getWarmupStatus());
        $this->assertSame(0, $warmup->getWarmupMaxToday());
        $this->assertSame(0, $warmup->getWarmupSentToday());
    }

    public function test_sets_day_0_max_on_first_day(): void
    {
        $schedule = array_fill(0, 30, 100);
        $schedule[0] = 50;

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => new \DateTimeImmutable('today', new \DateTimeZone('UTC')),
            'warmup_schedule' => $schedule,
            'warmup_sent_today' => 25,
            'warmup_max_today' => 0,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(0, $warmup->getWarmupSentToday());
        $this->assertSame(50, $warmup->getWarmupMaxToday());
    }

    public function test_ignores_ips_without_schedule_or_started_date(): void
    {
        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMING,
            'warmup_started_date' => null,
            'warmup_schedule' => null,
            'warmup_sent_today' => 50,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(50, $warmup->getWarmupSentToday());
    }

    public function test_ignores_warmed_ips(): void
    {
        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'warmup_status' => WarmupStatus::WARMED,
            'warmup_started_date' => null,
            'warmup_schedule' => null,
            'warmup_sent_today' => 50,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(50, $warmup->getWarmupSentToday());
    }

}
