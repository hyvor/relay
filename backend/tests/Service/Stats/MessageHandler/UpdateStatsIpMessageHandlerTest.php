<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\IpAddress;
use App\Entity\Project;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\Message\UpdateStatsIpMessage;
use App\Service\Stats\MessageHandler\UpdateStatsIpMessageHandler;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStatsIpMessageHandler::class)]
class UpdateStatsIpMessageHandlerTest extends WebTestCase
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
    private function assertAndGetRow(int $ipAddressId, string $stat_date_sql): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM stats_ip WHERE ip_address_id = ? AND stat_date = $stat_date_sql",
            [$ipAddressId]
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

        /** @var UpdateStatsIpMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpMessageHandler::class);
        $handler(new UpdateStatsIpMessage());

        $row = $this->assertAndGetRow($ipAddress->getId(), 'CURRENT_DATE');

        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(2, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertSame(1, (int)$row['bounced_unknown']);
    }

    public function test_updates_last_days_stats(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $this->createSendWithRecipients($project, $ipAddress, new \DateTimeImmutable('yesterday'));
        $this->createSendWithRecipients($project, $ipAddress, new \DateTimeImmutable('today'));

        /** @var UpdateStatsIpMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpMessageHandler::class);
        $handler(new UpdateStatsIpMessage(forLastDay: true));

        $row = $this->assertAndGetRow($ipAddress->getId(), "CURRENT_DATE - INTERVAL '1 day'");

        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(2, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['bounced_unknown']);

        $todayRow = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip WHERE ip_address_id = ? AND stat_date = CURRENT_DATE',
            [$ipAddress->getId()]
        );
        $this->assertFalse($todayRow, 'today\'s rollup should not run when forLastDay is true');
    }
}
