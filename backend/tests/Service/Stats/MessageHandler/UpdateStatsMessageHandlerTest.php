<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\Instance;
use App\Entity\IpAddress;
use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Instance\InstanceService;
use App\Service\Stats\Message\UpdateStatsMessage;
use App\Service\Stats\MessageHandler\UpdateStatsMessageHandler;
use App\Service\Stats\StatsService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProviderMetricFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendFeedbackFactory;
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
        SendRecipientStatus $status,
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
            'recipient_status' => $status,
            'bounced_reason' => $bounceReason,
            'created_at' => $date,
        ]);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|false
     */
    private function row(string $table, array $where): array|false
    {
        $conditions = array_map(
            fn(string $column) => $where[$column] === null ? "$column IS NULL" : "$column = :$column",
            array_keys($where)
        );

        return $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM $table WHERE " . implode(' AND ', $conditions),
            array_filter($where, fn($value) => $value !== null)
        );
    }

    private function getInstance(): Instance
    {
        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);
        return $instanceService->getInstance();
    }

    public function test_late_complaint_counts_on_acceptance_date(): void
    {
        Clock::set(new MockClock('2026-06-12 12:00:00'));
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $complained = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $accepted = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->attempt($send, $complained, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $accepted, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        $this->runHandler();

        $where = ['project_id' => $project->getId(), 'stat_date' => $date->format('Y-m-d')];

        $row = $this->row('stats_project', $where);
        $this->assertIsArray($row);
        $this->assertSame(2, $row['accepted']);
        $this->assertSame(0, $row['complained']);

        Clock::set(new MockClock('2026-06-15 12:00:00'));

        $complained->setStatus(SendRecipientStatus::COMPLAINED);
        $this->em->flush();

        $feedback = SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::COMPLAINT,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => $complained,
        ]);

        $this->runHandler();

        $row = $this->row('stats_project', $where);
        $this->assertIsArray($row);
        $this->assertSame(1, $row['sends']);
        $this->assertSame(2, $row['send_recipients']);
        $this->assertSame(2, $row['accepted']);
        $this->assertSame(1, $row['complained']);
        $this->assertSame('0.500000', $row['complained_rate']);

        $this->em->refresh($feedback);
        $this->assertNotNull($feedback->getProcessedAt());
    }

    public function test_multi_domain_send_does_not_leak_across_domains(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $gmail = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $yahoo = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::BOUNCED]);

        $this->attempt($send, $gmail, $ipAddress, 'gmail.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $yahoo, $ipAddress, 'yahoo.com', $date, SendRecipientStatus::BOUNCED, BounceReason::RECIPIENT);

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
    }

    public function test_async_bounce_counts_on_acceptance_date(): void
    {
        $date = $this->now()->modify('-5 days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $recipient = SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
        ]);

        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::BOUNCE,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => $recipient,
            'detail' => BounceReason::UNKNOWN->value,
        ]);

        $this->runHandler();

        $row = $this->row('stats_ip_project', [
            'ip_address_id' => $ipAddress->getId(),
            'project_id' => $project->getId(),
            'stat_date' => $date->format('Y-m-d'),
        ]);
        $this->assertIsArray($row);
        $this->assertSame(1, $row['accepted']);
        $this->assertSame(1, $row['bounced_unknown']);
    }

    public function test_redacted_complaint_counts_for_project_and_ip_only(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date, 'ip_address' => $ipAddress]);
        $first = SendRecipientFactory::createOne(['send' => $send]);
        $second = SendRecipientFactory::createOne(['send' => $send]);

        $this->attempt($send, $first, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $second, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        $feedback = SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::COMPLAINT,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => null,
            'ip_address' => $ipAddress,
        ]);

        $this->runHandler();

        $statDate = $date->format('Y-m-d');

        $this->em->refresh($feedback);
        $this->assertSame($statDate, $feedback->getStatDate()?->format('Y-m-d'));

        $projectRow = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $statDate]);
        $this->assertIsArray($projectRow);
        $this->assertSame(1, $projectRow['complained']);

        $ipRow = $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $statDate]);
        $this->assertIsArray($ipRow);
        $this->assertSame(1, $ipRow['complained']);

        $ipProjectRow = $this->row('stats_ip_project', [
            'ip_address_id' => $ipAddress->getId(),
            'project_id' => $project->getId(),
            'stat_date' => $statDate,
        ]);
        $this->assertIsArray($ipProjectRow);
        $this->assertSame(1, $ipProjectRow['complained']);

        $domainRow = $this->row('stats_delivery_domain', [
            'project_id' => $project->getId(),
            'ip_address_id' => $ipAddress->getId(),
            'recipient_domain' => 'example.com',
            'stat_date' => $statDate,
        ]);
        $this->assertIsArray($domainRow);
        $this->assertSame(0, $domainRow['complained']);
    }

    public function test_skips_when_another_run_holds_the_lock(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $recipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

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
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $oldDate]);
        $recipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $oldDate, SendRecipientStatus::ACCEPTED);

        $this->em->getConnection()->insert('stats_project', [
            'project_id' => $project->getId(),
            'stat_date' => $oldDate->format('Y-m-d'),
            'accepted' => 10,
        ]);

        $feedback = SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::COMPLAINT,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => $recipient,
        ]);

        $this->runHandler();

        $row = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $oldDate->format('Y-m-d')]);
        $this->assertIsArray($row);
        $this->assertSame(10, $row['accepted']);
        $this->assertSame(0, $row['complained']);

        $this->em->refresh($feedback);
        $this->assertNotNull($feedback->getProcessedAt());
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
            $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);
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

    public function test_catch_up_is_limited_to_rebuild_window(): void
    {
        $instance = $this->getInstance();
        $instance->setStatsRebuiltAt($this->now()->modify('-60 days'));
        $this->em->flush();

        $oldDate = $this->now()->modify('-' . (UpdateStatsMessageHandler::MAX_REBUILD_AGE_DAYS + 1) . ' days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $oldDate]);
        $recipient = SendRecipientFactory::createOne(['send' => $send]);
        $this->attempt($send, $recipient, $ipAddress, 'example.com', $oldDate, SendRecipientStatus::ACCEPTED);

        $this->runHandler();

        $this->assertFalse(
            $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $oldDate->format('Y-m-d')])
        );
    }

    public function test_counts_each_delivery_outcome(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'ip_address' => $ipAddress, 'created_at' => $date]);

        $outcomes = [
            [SendRecipientStatus::ACCEPTED, null],
            [SendRecipientStatus::DEFERRED, null],
            [SendRecipientStatus::BOUNCED, BounceReason::RECIPIENT],
            [SendRecipientStatus::BOUNCED, BounceReason::INFRASTRUCTURE],
            [SendRecipientStatus::FAILED, null],
        ];

        foreach ($outcomes as [$status, $bounceReason]) {
            $recipient = SendRecipientFactory::createOne(['send' => $send, 'status' => $status]);
            $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, $status, $bounceReason);
        }

        SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::SUPPRESSED]);

        $this->runHandler();

        $statDate = $date->format('Y-m-d');

        $rows = [
            $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $statDate]),
            $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $statDate]),
        ];

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $this->assertSame(1, $row['sends']);
            $this->assertSame(6, $row['send_recipients']);
            $this->assertSame(5, $row['send_attempts']);
            $this->assertSame(1, $row['accepted']);
            $this->assertSame(1, $row['deferred']);
            $this->assertSame(1, $row['bounced_recipient']);
            $this->assertSame(1, $row['bounced_infrastructure']);
            $this->assertSame(1, $row['failed']);
            $this->assertSame(1, $row['suppressed']);
            $this->assertSame('0.2000', $row['accepted_rate']);
            $this->assertSame('0.1667', $row['suppressed_rate']);
        }
    }

    public function test_google_metric_takes_priority_on_gmail(): void
    {
        $date = $this->now()->modify('-3 days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $gmailComplained = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);
        $gmailAccepted = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        $other = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);

        $this->attempt($send, $gmailComplained, $ipAddress, 'gmail.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $gmailAccepted, $ipAddress, 'gmail.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $other, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        foreach ([$gmailComplained, $other] as $recipient) {
            SendFeedbackFactory::createOne([
                'type' => SendFeedbackType::COMPLAINT,
                'project' => $project,
                'send' => $send,
                'sendRecipient' => $recipient,
            ]);
        }

        $this->runHandler();

        $where = [
            'project_id' => $project->getId(),
            'ip_address_id' => $ipAddress->getId(),
            'stat_date' => $date->format('Y-m-d'),
        ];

        $gmailRow = $this->row('stats_delivery_domain', [...$where, 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($gmailRow);
        $this->assertSame('0.500000', $gmailRow['complained_rate']);

        foreach (['0.002', '0.003'] as $value) {
            ProviderMetricFactory::createOne([
                'project' => $project,
                'ip_address' => $ipAddress,
                'metric_date' => $date,
                'value' => $value,
            ]);
        }

        ProviderMetricFactory::createOne([
            'project' => null,
            'ip_address' => $ipAddress,
            'metric_date' => $date,
            'value' => '0.004',
        ]);

        $this->runHandler();

        $gmailRow = $this->row('stats_delivery_domain', [...$where, 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($gmailRow);
        $this->assertSame(1, $gmailRow['complained']);
        $this->assertSame('0.003000', $gmailRow['complained_rate']);

        $otherRow = $this->row('stats_delivery_domain', [...$where, 'recipient_domain' => 'example.com']);
        $this->assertIsArray($otherRow);
        $this->assertSame('1.000000', $otherRow['complained_rate']);

        $ipOnlyRow = $this->row('stats_delivery_domain', [...$where, 'project_id' => null, 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($ipOnlyRow);
        $this->assertSame(0, $ipOnlyRow['sent']);
        $this->assertSame('0.004000', $ipOnlyRow['complained_rate']);

        $processed = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM provider_metrics WHERE processed_at IS NOT NULL');
        $this->assertSame(3, $processed);
    }
}
