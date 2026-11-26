<?php

namespace App\Tests\Service\Cloud\MeteredUsage;

use App\Entity\Project;
use App\Entity\Send;
use App\Service\Cloud\MeteredUsage\Command\ReportMeteredUsageCommand;
use App\Service\Cloud\MeteredUsage\Message\ReportMeteredUsageMessage;
use App\Service\Cloud\MeteredUsage\MessageHandler\ReportMeteredUsageMessageHandler;
use App\Service\Cloud\MeteredUsage\MeteredUsageService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\RecordMeteredUsage;
use Hyvor\Internal\Component\Component;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(MeteredUsageService::class)]
#[CoversClass(ReportMeteredUsageMessageHandler::class)]
#[CoversClass(ReportMeteredUsageCommand::class)]
class ReportMeteredUsageTest extends KernelTestCase
{

    use ClockSensitiveTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnvVar('DEPLOYMENT', 'cloud');
    }

    /**
     * @param string[] $recipients
     */
    private function createSend(Project $project, string $createdAt, array $recipients): Send
    {
        $send = SendFactory::createOne([
            'project' => $project,
            'createdAt' => new \DateTimeImmutable($createdAt),
        ]);

        foreach ($recipients as $address) {
            SendRecipientFactory::createOne([
                'send' => $send,
                'address' => $address,
            ]);
        }

        return $send;
    }

    private function seed(): void
    {
        $org1ProjectA = ProjectFactory::createOne(['organization_id' => 1]);
        $org1ProjectB = ProjectFactory::createOne(['organization_id' => 1]);
        $org2Project = ProjectFactory::createOne(['organization_id' => 2]);
        $org3Project = ProjectFactory::createOne(['organization_id' => 3]);

        // org 1: 2 + 1 + 1 = 4 (across two projects, simulator excluded)
        $this->createSend($org1ProjectA, '2026-11-25 00:00:00', ['a@example.com', 'b@example.com']);
        $this->createSend($org1ProjectB, '2026-11-25 12:00:00', ['c@example.com', 'x@simulator.relay.hyvor.com']);
        $this->createSend($org1ProjectB, '2026-11-25 23:59:59', ['d@example.com']);

        // org 2: 1
        $this->createSend($org2Project, '2026-11-25 08:00:00', ['e@example.com']);

        // org 3: only simulator recipients -> not reported
        $this->createSend($org3Project, '2026-11-25 08:00:00', ['y@SIMULATOR.relay.hyvor.com']);

        // other days -> not counted
        $this->createSend($org1ProjectA, '2026-11-24 23:59:59', ['g@example.com']);
        $this->createSend($org2Project, '2026-11-26 00:00:00', ['h@example.com']);
    }

    public function test_daily_usage_by_organization(): void
    {
        $this->seed();

        $service = $this->getService(MeteredUsageService::class);
        $usage = $service->getDailyUsageByOrganization(new \DateTimeImmutable('2026-11-25'));

        $this->assertSame([1 => 4, 2 => 1], $usage);
    }

    /**
     * @return array<string, array<int, RecordMeteredUsage>> date => organization_id => event
     */
    private function sentEvents(): array
    {
        $events = [];
        foreach ($this->getComms()->getSentsByEventClass(RecordMeteredUsage::class) as $sent) {
            $this->assertTrue($sent['async']);
            $this->assertSame(Component::CORE, $sent['to']);

            $event = $sent['event'];
            $this->assertInstanceOf(RecordMeteredUsage::class, $event);
            $events[$event->getUsageDate()->format('Y-m-d')][$event->getOrganizationId()] = $event;
        }
        ksort($events);
        return $events;
    }

    private function runHandler(): void
    {
        $transport = $this->transport('scheduler_cloud');
        $transport->send(new ReportMeteredUsageMessage());
        $transport->throwExceptions()->process();
    }

    private function setLastReportedDay(string $day): void
    {
        $cache = $this->getService(CacheItemPoolInterface::class);
        $item = $cache->getItem(ReportMeteredUsageMessageHandler::LAST_REPORTED_DAY_CACHE_KEY);
        $item->set($day);
        $cache->save($item);
    }

    private function getLastReportedDay(): mixed
    {
        return $this->getService(CacheItemPoolInterface::class)
            ->getItem(ReportMeteredUsageMessageHandler::LAST_REPORTED_DAY_CACHE_KEY)
            ->get();
    }

    public function test_first_run_reports_yesterday(): void
    {
        $this->mockTime('2026-11-26 00:15:00');
        $this->seed();

        $this->runHandler();

        $events = $this->sentEvents();
        $this->assertSame(['2026-11-25'], array_keys($events));

        $org1 = $events['2026-11-25'][1];
        $this->assertSame(4, $org1->getAmount());
        $this->assertSame('relay:1:2026-11-25', $org1->getIdempotencyKey());
        $this->assertSame(Component::RELAY, $org1->getComponent());

        $org2 = $events['2026-11-25'][2];
        $this->assertSame(1, $org2->getAmount());
        $this->assertSame('relay:2:2026-11-25', $org2->getIdempotencyKey());

        $this->assertSame('2026-11-25', $this->getLastReportedDay());
    }

    public function test_catches_up_missed_days(): void
    {
        // worker was down: last report was for the 22nd, now running late on the 26th
        $this->mockTime('2026-11-26 10:00:00');
        $this->setLastReportedDay('2026-11-22');
        $this->seed();

        $this->runHandler();

        $events = $this->sentEvents();
        // 23rd has no usage; 24th, 25th have usage
        $this->assertSame(['2026-11-24', '2026-11-25'], array_keys($events));
        $this->assertSame([1], array_keys($events['2026-11-24']));
        $this->assertSame(1, $events['2026-11-24'][1]->getAmount());
        $this->assertSame([1, 2], array_keys($events['2026-11-25']));

        $this->assertSame('2026-11-25', $this->getLastReportedDay());
    }

    public function test_does_nothing_when_already_reported(): void
    {
        $this->mockTime('2026-11-26 00:15:00');
        $this->setLastReportedDay('2026-11-25');
        $this->seed();

        $this->runHandler();

        $this->assertSame([], $this->sentEvents());
        $this->assertSame('2026-11-25', $this->getLastReportedDay());
    }

    public function test_catch_up_is_limited_to_send_retention(): void
    {
        $this->mockTime('2026-11-26 00:15:00');
        $this->setLastReportedDay('2026-09-01');

        $this->runHandler();

        $this->assertSame('2026-11-25', $this->getLastReportedDay());
        $this->assertTrue($this->getTestLogger()->hasErrorThatContains('cannot be reported'));

        // earliest reported day: 29 days back from yesterday (inclusive)
        $reportedDays = [];
        foreach ($this->getTestLogger()->getRecords() as $record) {
            if ($record->message === 'Metered usage reported') {
                $reportedDays[] = $record->context['date'];
            }
        }
        $this->assertCount(ReportMeteredUsageMessageHandler::MAX_CATCH_UP_DAYS, $reportedDays);
        $this->assertSame('2026-10-28', $reportedDays[0]);
    }

    public function test_handler_does_nothing_when_not_cloud(): void
    {
        $this->setEnvVar('DEPLOYMENT', 'on-prem');
        $this->mockTime('2026-11-26 00:15:00');
        $this->seed();

        $this->runHandler();

        $this->getComms()->assertNotSent(RecordMeteredUsage::class, Component::CORE);
        $this->assertNull($this->getLastReportedDay());
    }

    public function test_command_reports_given_date(): void
    {
        $this->seed();

        $command = $this->commandTester('cloud:report-metered-usage');
        $exitCode = $command->execute(['--date' => '2026-11-25']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(2, $this->getComms()->getSentsByEventClass(RecordMeteredUsage::class));
    }

    public function test_command_requires_valid_date(): void
    {
        $command = $this->commandTester('cloud:report-metered-usage');

        $this->assertSame(2, $command->execute([]));
        $this->assertSame(2, $command->execute(['--date' => '2026-13-45']));
        $this->assertSame(2, $command->execute(['--date' => 'yesterday']));
    }

}
