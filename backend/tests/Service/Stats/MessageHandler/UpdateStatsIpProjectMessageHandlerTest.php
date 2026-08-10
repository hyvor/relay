<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\IpAddress;
use App\Entity\Project;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\Message\UpdateStatsIpProjectMessage;
use App\Service\Stats\MessageHandler\UpdateStatsIpProjectMessageHandler;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStatsIpProjectMessageHandler::class)]
class UpdateStatsIpProjectMessageHandlerTest extends WebTestCase
{
    private function createSendWithRecipients(Project $project, IpAddress $ipAddress, \DateTimeImmutable $createdAt): void
    {
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => $createdAt,
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
            'address' => 'a@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
            'bounced_reason' => BounceReason::UNKNOWN,
            'address' => 'b@example.com',
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'ip_address' => $ipAddress,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function assertAndGetRow(int $ipAddressId, int $projectId, string $stat_date_sql): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM stats_ip_project WHERE ip_address_id = ? AND project_id = ? AND stat_date = $stat_date_sql",
            [$ipAddressId, $projectId]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        return $row;
    }

    public function test_updates_todays_stats(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $this->createSendWithRecipients($project, $ipAddress, new \DateTimeImmutable('today'));

        /** @var UpdateStatsIpProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpProjectMessageHandler::class);
        $handler(new UpdateStatsIpProjectMessage());

        $row = $this->assertAndGetRow($ipAddress->getId(), $project->getId(), 'CURRENT_DATE');

        $this->assertSame(2, (int)$row['sent']);
        $this->assertSame(1, (int)$row['bounced_unknown']);
        $this->assertGreaterThan(0, (float)$row['bounced_unknown_rate']);
    }

    public function test_updates_last_days_stats(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $this->createSendWithRecipients($project, $ipAddress, new \DateTimeImmutable('yesterday'));
        $this->createSendWithRecipients($project, $ipAddress, new \DateTimeImmutable('today'));

        /** @var UpdateStatsIpProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpProjectMessageHandler::class);
        $handler(new UpdateStatsIpProjectMessage(forLastDay: true));

        $row = $this->assertAndGetRow($ipAddress->getId(), $project->getId(), "CURRENT_DATE - INTERVAL '1 day'");

        $this->assertSame(2, (int)$row['sent']);
        $this->assertSame(1, (int)$row['bounced_unknown']);

        $todayRow = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip_project WHERE ip_address_id = ? AND project_id = ? AND stat_date = CURRENT_DATE',
            [$ipAddress->getId(), $project->getId()]
        );
        $this->assertFalse($todayRow, 'today\'s rollup should not run when forLastDay is true');
    }
}
