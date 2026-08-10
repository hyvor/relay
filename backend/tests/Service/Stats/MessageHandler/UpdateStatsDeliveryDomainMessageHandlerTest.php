<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\IpAddress;
use App\Entity\Project;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\Message\UpdateStatsDeliveryDomainMessage;
use App\Service\Stats\MessageHandler\UpdateStatsDeliveryDomainMessageHandler;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStatsDeliveryDomainMessageHandler::class)]
class UpdateStatsDeliveryDomainMessageHandlerTest extends WebTestCase
{
    private function createSendWithRecipients(
        Project $project,
        IpAddress $ipAddress,
        string $domain,
        \DateTimeImmutable $createdAt
    ): void {
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
            'domain' => $domain,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function assertAndGetRow(int $projectId, int $ipAddressId, string $domain, string $stat_date_sql): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM stats_delivery_domain
             WHERE project_id = ? AND ip_address_id = ? AND recipient_domain = ? AND stat_date = $stat_date_sql",
            [$projectId, $ipAddressId, $domain]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        return $row;
    }

    public function test_updates_todays_stats(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $this->createSendWithRecipients($project, $ipAddress, 'example.com', new \DateTimeImmutable('today'));

        /** @var UpdateStatsDeliveryDomainMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsDeliveryDomainMessageHandler::class);
        $handler(new UpdateStatsDeliveryDomainMessage());

        $row = $this->assertAndGetRow($project->getId(), $ipAddress->getId(), 'example.com', 'CURRENT_DATE');

        $this->assertSame(2, (int)$row['sent']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertSame(1, (int)$row['bounced_unknown']);
        $this->assertNull($row['provider']);
    }

    public function test_updates_last_days_stats(): void
    {
        $project = ProjectFactory::createOne();
        $ipAddress = IpAddressFactory::createOne();
        $this->createSendWithRecipients($project, $ipAddress, 'example.com', new \DateTimeImmutable('yesterday'));
        $this->createSendWithRecipients($project, $ipAddress, 'example.com', new \DateTimeImmutable('today'));

        /** @var UpdateStatsDeliveryDomainMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsDeliveryDomainMessageHandler::class);
        $handler(new UpdateStatsDeliveryDomainMessage(forLastDay: true));

        $row = $this->assertAndGetRow(
            $project->getId(),
            $ipAddress->getId(),
            'example.com',
            "CURRENT_DATE - INTERVAL '1 day'"
        );

        $this->assertSame(2, (int)$row['sent']);
        $this->assertSame(1, (int)$row['bounced_unknown']);

        $todayRow = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_delivery_domain
             WHERE project_id = ? AND ip_address_id = ? AND recipient_domain = ? AND stat_date = CURRENT_DATE',
            [$project->getId(), $ipAddress->getId(), 'example.com']
        );
        $this->assertFalse($todayRow, 'today\'s rollup should not run when forLastDay is true');
    }
}
