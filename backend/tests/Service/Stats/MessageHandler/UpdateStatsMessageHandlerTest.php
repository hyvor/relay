<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\Instance;
use App\Entity\IpAddress;
use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptRecipientStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Instance\InstanceService;
use App\Service\Stats\Message\UpdateStatsMessage;
use App\Service\Stats\MessageHandler\UpdateStatsMessageHandler;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Stats\StatsService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(UpdateStatsMessageHandler::class)]
#[CoversClass(StatsService::class)]
class UpdateStatsMessageHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Clock::set(new MockClock('2026-06-15 12:00:00'));
    }

    private function runHandler(): void
    {
        /** @var UpdateStatsMessageHandler $handler */
        $handler = $this->container->get(UpdateStatsMessageHandler::class);
        $handler(new UpdateStatsMessage());
    }

    private function attempt(
        Send $send,
        SendRecipient $recipient,
        IpAddress $ipAddress,
        string $domain,
        \DateTimeImmutable $date,
        SendAttemptRecipientStatus $status,
        ?BounceReason $bounceReason = null,
    ): void {
        SendAttemptRecipientFactory::createOne([
            'send_attempt' => SendAttemptFactory::createOne([
                'send' => $send,
                'ip_address' => $ipAddress,
                'domain' => $domain,
                'created_at' => $date,
            ]),
            'send_recipient_id' => $recipient->getId(),
            'status' => $status,
            'bounced_reason' => $bounceReason ?? BounceReason::UNKNOWN,
            'created_at' => $date,
        ]);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|false
     */
    private function row(string $table, array $where): array|false
    {
        $conditions = array_map(fn(string $column) => "$column = :$column", array_keys($where));

        return $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM $table WHERE " . implode(' AND ', $conditions),
            $where
        );
    }

    private function markOutdated(\DateTimeImmutable $date): void
    {
        /** @var StatsService $statsService */
        $statsService = $this->container->get(StatsService::class);
        $statsService->markDateOutdated($date);
    }

    private function isOutdated(\DateTimeImmutable $date): bool
    {
        return $this->row('stats_outdated_dates', ['stat_date' => $date->format('Y-m-d')]) !== false;
    }

    private function getInstance(): Instance
    {
        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);
        return $instanceService->getInstance();
    }

    public function test_project_stats_count_recipients_once_by_status(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'ip_address' => $ipAddress, 'created_at' => $date]);

        // deferred first, then accepted on a later day: counted once, as accepted
        $retried = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $this->attempt($send, $retried, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::DEFERRED);
        $this->attempt($send, $retried, $ipAddress, 'example.com', $date->modify('+1 day'), SendAttemptRecipientStatus::ACCEPTED);

        $complained = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);
        $this->attempt($send, $complained, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::ACCEPTED);

        foreach ([BounceReason::RECIPIENT, BounceReason::INFRASTRUCTURE, BounceReason::UNKNOWN] as $reason) {
            $bounced = SendRecipientFactory::createOne([
                'send' => $send,
                'status' => SendRecipientStatus::BOUNCED,
                'bounce_reason' => $reason,
            ]);
            $this->attempt($send, $bounced, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::BOUNCED, $reason);
        }

        foreach ([
            SendRecipientStatus::QUEUED,
            SendRecipientStatus::DEFERRED,
            SendRecipientStatus::FAILED,
            SendRecipientStatus::SUPPRESSED,
        ] as $status) {
            SendRecipientFactory::createOne(['send' => $send, 'status' => $status]);
        }

        $this->runHandler();

        $row = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $date->format('Y-m-d')]);
        $this->assertIsArray($row);
        $this->assertSame(1, $row['sends']);
        $this->assertSame(9, $row['send_recipients']);
        $this->assertSame(1, $row['queued']);
        $this->assertSame(1, $row['accepted']); // does not include the complained one
        $this->assertSame(1, $row['deferred']);
        $this->assertSame(1, $row['bounced_recipient']);
        $this->assertSame(1, $row['bounced_infrastructure']);
        $this->assertSame(1, $row['bounced_unknown']);
        $this->assertSame(1, $row['complained']);
        $this->assertSame(1, $row['suppressed']);
        $this->assertSame(1, $row['failed']);
        $this->assertSame('0.1111', $row['accepted_rate']);
        $this->assertSame('0.500000', $row['complained_rate']);
        $this->assertArrayNotHasKey('attempted', $row);
        $this->assertArrayNotHasKey('send_attempts', $row);

        // the day after has no recipients added, so no project stats
        $this->assertFalse($this->row('stats_project', [
            'project_id' => $project->getId(),
            'stat_date' => $date->modify('+1 day')->format('Y-m-d'),
        ]));
    }

    public function test_ip_stats_count_every_attempt(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'ip_address' => $ipAddress, 'created_at' => $date]);

        $retried = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $this->attempt($send, $retried, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::DEFERRED);
        $this->attempt($send, $retried, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::ACCEPTED);

        // a complained recipient was accepted by the server (the complaint does not change the attempt)
        $complained = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);
        $this->attempt($send, $complained, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::ACCEPTED);

        $outcomes = [
            [SendRecipientStatus::BOUNCED, SendAttemptRecipientStatus::BOUNCED, BounceReason::RECIPIENT],
            [SendRecipientStatus::BOUNCED, SendAttemptRecipientStatus::BOUNCED, BounceReason::INFRASTRUCTURE],
            [SendRecipientStatus::BOUNCED, SendAttemptRecipientStatus::BOUNCED, BounceReason::UNKNOWN],
            [SendRecipientStatus::FAILED, SendAttemptRecipientStatus::FAILED, null],
        ];
        foreach ($outcomes as [$status, $attemptStatus, $reason]) {
            $recipient = SendRecipientFactory::createOne(['send' => $send, 'status' => $status]);
            $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, $attemptStatus, $reason);
        }

        SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::SUPPRESSED]);

        $this->runHandler();

        $row = $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $date->format('Y-m-d')]);
        $this->assertIsArray($row);
        $this->assertSame(1, $row['sends']);
        $this->assertSame(7, $row['send_recipients']);
        $this->assertSame(7, $row['send_attempts']);
        $this->assertSame(7, $row['attempted']);
        $this->assertSame(2, $row['accepted']); // accepted + the one that complained later
        $this->assertSame(1, $row['deferred']);
        $this->assertSame(1, $row['bounced_recipient']);
        $this->assertSame(1, $row['bounced_infrastructure']);
        $this->assertSame(1, $row['bounced_unknown']);
        $this->assertSame(1, $row['failed']);
        $this->assertArrayNotHasKey('complained', $row);
        $this->assertArrayNotHasKey('complained_rate', $row);
        $this->assertSame('0.2857', $row['accepted_rate']);
        $this->assertSame('0.1429', $row['deferred_rate']);
        $this->assertArrayNotHasKey('suppressed', $row);
        $this->assertArrayNotHasKey('suppressed_rate', $row);
    }

    public function test_attempt_stats_use_the_day_of_the_attempt(): void
    {
        $day1 = $this->now()->modify('-3 days');
        $day2 = $this->now()->modify('-2 days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'ip_address' => $ipAddress, 'created_at' => $day1]);
        $recipient = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->attempt($send, $recipient, $ipAddress, 'example.com', $day1, SendAttemptRecipientStatus::DEFERRED);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $day2, SendAttemptRecipientStatus::ACCEPTED);

        $this->markOutdated($day1);
        $this->markOutdated($day2);
        $this->runHandler();

        $row1 = $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $day1->format('Y-m-d')]);
        $row2 = $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $day2->format('Y-m-d')]);
        $this->assertIsArray($row1);
        $this->assertIsArray($row2);
        $this->assertSame(1, $row1['deferred']);
        $this->assertSame(0, $row1['accepted']);
        $this->assertSame(0, $row2['deferred']);
        $this->assertSame(1, $row2['accepted']);

        // the recipient is counted once, on the day it was added
        $project1 = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $day1->format('Y-m-d')]);
        $this->assertIsArray($project1);
        $this->assertSame(1, $project1['accepted']);
        $this->assertFalse($this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $day2->format('Y-m-d')]));
    }

    public function test_multi_domain_send_does_not_leak_across_domains(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $gmail = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $yahoo = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::BOUNCED]);

        $this->attempt($send, $gmail, $ipAddress, 'gmail.com', $date, SendAttemptRecipientStatus::ACCEPTED);
        $this->attempt($send, $yahoo, $ipAddress, 'yahoo.com', $date, SendAttemptRecipientStatus::BOUNCED, BounceReason::RECIPIENT);

        $this->runHandler();

        $where = ['project_id' => $project->getId(), 'ip_address_id' => $ipAddress->getId(), 'stat_date' => $date->format('Y-m-d')];

        $gmailRow = $this->row('stats_delivery_domain', [...$where, 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($gmailRow);
        $this->assertSame(1, $gmailRow['sent']);
        $this->assertSame(1, $gmailRow['accepted']);
        $this->assertSame(0, $gmailRow['bounced_recipient']);

        $yahooRow = $this->row('stats_delivery_domain', [...$where, 'recipient_domain' => 'yahoo.com']);
        $this->assertIsArray($yahooRow);
        $this->assertSame(1, $yahooRow['sent']);
        $this->assertSame(0, $yahooRow['accepted']);
        $this->assertSame(1, $yahooRow['bounced_recipient']);

        $ipProject = $this->row('stats_ip_project', $where);
        $this->assertIsArray($ipProject);
        $this->assertSame(2, $ipProject['sent']);
    }

    public function test_async_bounce_does_not_change_attempt_stats(): void
    {
        $date = $this->now()->modify('-5 days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'ip_address' => $ipAddress, 'created_at' => $date]);
        $recipient = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::DEFERRED);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date->modify('+1 hour'), SendAttemptRecipientStatus::ACCEPTED);

        $this->markOutdated($date);
        $this->runHandler();
        $statDate = $date->format('Y-m-d');
        $ipWhere = ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $statDate];
        $projectWhere = ['project_id' => $project->getId(), 'stat_date' => $statDate];
        $this->assertSame(1, $this->row('stats_ip', $ipWhere)['accepted'] ?? null);
        $this->assertFalse($this->isOutdated($date));

        /** @var SendRecipientService $recipientService */
        $recipientService = $this->container->get(SendRecipientService::class);
        $recipientService->updateSendRecipientStatus($recipient, SendRecipientStatus::BOUNCED, BounceReason::RECIPIENT);
        $this->assertTrue($this->isOutdated($date));

        $this->runHandler();

        $this->assertFalse($this->isOutdated($date));
        $ip = $this->row('stats_ip', $ipWhere);
        $this->assertIsArray($ip);
        // the attempts keep what the SMTP server returned at that moment
        $this->assertSame(2, $ip['attempted']);
        $this->assertSame(1, $ip['deferred']);
        $this->assertSame(1, $ip['accepted']);
        $this->assertSame(0, $ip['bounced_recipient']);

        $project = $this->row('stats_project', $projectWhere);
        $this->assertIsArray($project);
        $this->assertSame(1, $project['send_recipients']);
        $this->assertSame(1, $project['bounced_recipient']);
        $this->assertSame(0, $project['accepted']);
    }

    public function test_recipient_status_update_flags_the_day_the_recipient_was_added(): void
    {
        $day = $this->now()->modify('-3 days');
        $send = SendFactory::createOne(['created_at' => $day]);
        $recipient = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        /** @var SendRecipientService $service */
        $service = $this->container->get(SendRecipientService::class);
        $service->updateSendRecipientStatus($recipient, SendRecipientStatus::COMPLAINED);

        $this->assertTrue($this->isOutdated($day));
        $this->assertFalse($this->isOutdated($day->modify('+1 day')));
    }

    public function test_today_is_never_flagged(): void
    {
        $this->markOutdated($this->now());
        $this->assertFalse($this->isOutdated($this->now()));
    }

    public function test_skips_when_another_run_holds_the_lock(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $recipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::ACCEPTED);

        /** @var LockFactory $lockFactory */
        $lockFactory = $this->container->get(LockFactory::class);
        $lock = $lockFactory->createLock(UpdateStatsMessageHandler::LOCK_NAME);
        $this->assertTrue($lock->acquire());

        try {
            $this->runHandler();
        } finally {
            $lock->release();
        }

        $where = ['project_id' => $project->getId(), 'stat_date' => $date->format('Y-m-d')];
        $this->assertFalse($this->row('stats_project', $where));

        $this->runHandler();

        $row = $this->row('stats_project', $where);
        $this->assertIsArray($row);
        $this->assertSame(1, $row['accepted']);
    }

    public function test_does_not_rebuild_dates_past_retention_window(): void
    {
        $oldDate = $this->now()->modify('-' . (UpdateStatsMessageHandler::MAX_REBUILD_AGE_DAYS + 1) . ' days');
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $oldDate]);
        SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->em->getConnection()->insert('stats_project', [
            'project_id' => $project->getId(),
            'stat_date' => $oldDate->format('Y-m-d'),
            'accepted' => 10,
        ]);
        $this->markOutdated($oldDate);

        $this->runHandler();

        $row = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $oldDate->format('Y-m-d')]);
        $this->assertIsArray($row);
        $this->assertSame(10, $row['accepted']);
        $this->assertTrue($this->isOutdated($oldDate));
    }

    public function test_catches_up_on_dates_missed_while_scheduler_was_down(): void
    {
        Clock::set(new MockClock('2026-06-10 12:00:00'));
        $this->runHandler();

        $instance = $this->getInstance();
        $this->assertSame('2026-06-10 12:00:00', $instance->getStatsRebuiltAt()?->format('Y-m-d H:i:s'));

        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();

        foreach (['2026-06-10 18:00:00', '2026-06-12 09:00:00'] as $time) {
            $date = new \DateTimeImmutable($time);
            $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
            $recipient = SendRecipientFactory::createOne(['send' => $send]);
            $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendAttemptRecipientStatus::ACCEPTED);
        }

        Clock::set(new MockClock('2026-06-15 12:00:00'));
        $this->runHandler();

        foreach (['2026-06-10', '2026-06-12'] as $statDate) {
            $row = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $statDate]);
            $this->assertIsArray($row, "missing stats for $statDate");
            $this->assertSame(1, $row['accepted']);
        }

        $this->em->refresh($instance);
        $this->assertSame('2026-06-15 12:00:00', $instance->getStatsRebuiltAt()?->format('Y-m-d H:i:s'));
    }
}
