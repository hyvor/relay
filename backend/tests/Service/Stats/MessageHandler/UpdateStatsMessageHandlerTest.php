<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\IpAddress;
use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SendRecipientStatus;
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

    public function test_late_complaint_updates_acceptance_date(): void
    {
        $date = $this->now()->modify('-3 days');
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $complained = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);
        $accepted = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->attempt($send, $complained, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $accepted, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        $feedback = SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::COMPLAINT,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => $complained,
        ]);

        $this->runHandler();
        $this->runHandler();

        $row = $this->row('stats_project', ['project_id' => $project->getId(), 'stat_date' => $date->format('Y-m-d')]);
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

        $ipRow = $this->row('stats_ip', ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $date->format('Y-m-d')]);
        $this->assertIsArray($ipRow);
        $this->assertSame(1, $ipRow['accepted']);
        $this->assertSame(1, $ipRow['bounced_recipient']);
        $this->assertSame('0.5000', $ipRow['bounced_recipient_rate']);
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
            'bounced_reason' => BounceReason::UNKNOWN,
        ]);

        $this->attempt($send, $recipient, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::BOUNCE,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => $recipient,
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

        SendFeedbackFactory::createOne([
            'type' => SendFeedbackType::COMPLAINT,
            'project' => $project,
            'send' => $send,
            'sendRecipient' => null,
            'ip_address' => $ipAddress,
        ]);

        $this->runHandler();

        $statDate = $date->format('Y-m-d');

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

    public function test_google_metric_takes_priority_on_gmail(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $gmail = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);
        $other = SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::COMPLAINED]);

        $this->attempt($send, $gmail, $ipAddress, 'gmail.com', $date, SendRecipientStatus::ACCEPTED);
        $this->attempt($send, $other, $ipAddress, 'example.com', $date, SendRecipientStatus::ACCEPTED);

        foreach ([$gmail, $other] as $recipient) {
            SendFeedbackFactory::createOne([
                'type' => SendFeedbackType::COMPLAINT,
                'project' => $project,
                'send' => $send,
                'sendRecipient' => $recipient,
            ]);
        }

        foreach ([['0.002', $project], ['0.003', $project], ['0.004', null]] as [$value, $metricProject]) {
            ProviderMetricFactory::createOne([
                'project' => $metricProject,
                'ip_address' => $ipAddress,
                'metric_date' => $date,
                'value' => $value,
            ]);
        }

        $this->runHandler();

        $where = ['ip_address_id' => $ipAddress->getId(), 'stat_date' => $date->format('Y-m-d')];

        $gmailRow = $this->row('stats_delivery_domain', [...$where, 'project_id' => $project->getId(), 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($gmailRow);
        $this->assertSame(1, $gmailRow['complained']);
        $this->assertSame('0.003000', $gmailRow['complained_rate']);

        $otherRow = $this->row('stats_delivery_domain', [...$where, 'project_id' => $project->getId(), 'recipient_domain' => 'example.com']);
        $this->assertIsArray($otherRow);
        $this->assertSame('1.000000', $otherRow['complained_rate']);

        $ipOnlyRow = $this->row('stats_delivery_domain', [...$where, 'project_id' => null, 'recipient_domain' => 'gmail.com']);
        $this->assertIsArray($ipOnlyRow);
        $this->assertSame(0, $ipOnlyRow['sent']);
        $this->assertSame('0.004000', $ipOnlyRow['complained_rate']);

        $processed = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM provider_metrics WHERE processed_at IS NOT NULL');
        $this->assertSame(3, $processed);
    }

    public function test_gmail_falls_back_to_local_rate(): void
    {
        $date = $this->now();
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $send = SendFactory::createOne(['project' => $project, 'created_at' => $date]);
        $recipient = SendRecipientFactory::createOne(['send' => $send]);

        $this->attempt($send, $recipient, $ipAddress, 'gmail.com', $date, SendRecipientStatus::ACCEPTED);

        $this->runHandler();

        $row = $this->row('stats_delivery_domain', [
            'project_id' => $project->getId(),
            'ip_address_id' => $ipAddress->getId(),
            'recipient_domain' => 'gmail.com',
            'stat_date' => $date->format('Y-m-d'),
        ]);
        $this->assertIsArray($row);
        $this->assertSame('0.000000', $row['complained_rate']);
    }

    public function test_removes_rows_without_source_data(): void
    {
        $project = ProjectFactory::createOne();

        $this->em->getConnection()->executeStatement(
            'INSERT INTO stats_project (project_id, stat_date, sends) VALUES (:project, :date, 5)',
            ['project' => $project->getId(), 'date' => $this->now()->format('Y-m-d')]
        );

        $this->runHandler();

        $this->assertFalse($this->row('stats_project', ['project_id' => $project->getId()]));
    }
}
