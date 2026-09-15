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
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')),
            'schedule' => $schedule,
            'sent_today' => 50,
            'max_today' => 100,
            'results' => [],
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(0, $warmup->getSentToday());
        $this->assertSame(200, $warmup->getMaxToday());
        $this->assertSame(WarmupStatus::WARMING, $warmup->getStatus());
        $this->assertSame([50], $warmup->getResults());
    }

    public function test_auto_transitions_to_warmed_after_day_30(): void
    {
        $schedule = array_fill(0, 30, 100);

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('-31 days', new \DateTimeZone('UTC')),
            'schedule' => $schedule,
            'sent_today' => 50,
            'max_today' => 100,
            'results' => [],
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(WarmupStatus::WARMED, $warmup->getStatus());
        $this->assertSame(0, $warmup->getMaxToday());
        $this->assertSame(0, $warmup->getSentToday());
        $this->assertSame([50], $warmup->getResults());
    }

    public function test_sets_day_0_max_on_first_day(): void
    {
        $schedule = array_fill(0, 30, 100);
        $schedule[0] = 50;

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('today', new \DateTimeZone('UTC')),
            'schedule' => $schedule,
            'sent_today' => 25,
            'max_today' => 0,
            'results' => [],
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(0, $warmup->getSentToday());
        $this->assertSame(50, $warmup->getMaxToday());
        $this->assertSame([25], $warmup->getResults());
    }

    public function test_ignores_warmed_ips(): void
    {
        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMED,
            'started_date' => new \DateTimeImmutable('2026-05-01'),
            'schedule' => array_fill(0, 30, 100),
            'sent_today' => 50,
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame(50, $warmup->getSentToday());
    }

    public function test_appends_results_on_each_reset(): void
    {
        $schedule = array_fill(0, 30, 100);
        $schedule[1] = 200;

        $ip = IpAddressFactory::createOne();

        $warmup = WarmupScheduleFactory::createOne([
            'ipAddress' => $ip,
            'status' => WarmupStatus::WARMING,
            'started_date' => new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')),
            'schedule' => $schedule,
            'sent_today' => 75,
            'max_today' => 100,
            'results' => [42],
        ]);

        $transport = $this->transport(MessageTransport::ASYNC);
        $transport->send(new ResetIpWarmupMessage());
        $transport->throwExceptions()->process();

        refresh($warmup);

        $this->assertSame([42, 75], $warmup->getResults());
    }

}
