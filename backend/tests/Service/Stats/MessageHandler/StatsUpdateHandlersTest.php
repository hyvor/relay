<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Service\Stats\Message\UpdateStatsDeliveryDomainMessage;
use App\Service\Stats\Message\UpdateStatsIpMessage;
use App\Service\Stats\Message\UpdateStatsIpProjectMessage;
use App\Service\Stats\Message\UpdateStatsProjectMessage;
use App\Service\Stats\MessageHandler\UpdateStatsDeliveryDomainMessageHandler;
use App\Service\Stats\MessageHandler\UpdateStatsIpMessageHandler;
use App\Service\Stats\MessageHandler\UpdateStatsIpProjectMessageHandler;
use App\Service\Stats\MessageHandler\UpdateStatsProjectMessageHandler;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendAttemptRecipientFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\BounceReason;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStatsProjectMessageHandler::class)]
#[CoversClass(UpdateStatsIpMessageHandler::class)]
#[CoversClass(UpdateStatsIpProjectMessageHandler::class)]
#[CoversClass(UpdateStatsDeliveryDomainMessageHandler::class)]
class StatsUpdateHandlersTest extends WebTestCase
{
    public function test_update_stats_project(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable(),
        ]);

        // accepted recipient
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
            'address' => 'a@example.com',
        ]);

        // bounced recipient
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
            'bounced_reason' => BounceReason::RECIPIENT,
            'address' => 'b@example.com',
        ]);

        // infrastructure bounce
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
            'bounced_reason' => BounceReason::INFRASTRUCTURE,
            'address' => 'c@example.com',
        ]);

        // complained recipient
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::COMPLAINED,
            'address' => 'd@example.com',
        ]);

        // suppressed recipient
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::SUPPRESSED,
            'address' => 'e@example.com',
        ]);

        // failed recipient
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
            'address' => 'f@example.com',
        ]);

        // Create send attempt for today
        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'created_at' => new \DateTimeImmutable(),
        ]);

        /** @var UpdateStatsProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsProjectMessageHandler::class);
        $handler(new UpdateStatsProjectMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_project WHERE project_id = ? AND stat_date = CURRENT_DATE',
            [$project->getId()]
        );

        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(6, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertSame(0, (int)$row['deferred']);
        $this->assertSame(1, (int)$row['bounced_recipient']);
        $this->assertSame(1, (int)$row['bounced_infrastructure']);
        $this->assertSame(1, (int)$row['complained']);
        $this->assertSame(1, (int)$row['suppressed']);
        $this->assertSame(1, (int)$row['failed']);
        $this->assertGreaterThan(0, (float)$row['accepted_rate']);
    }

    public function test_update_stats_ip(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable(),
        ]);
        $ipAddress = IpAddressFactory::createOne();

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'ip_address' => $ipAddress,
            'created_at' => new \DateTimeImmutable(),
        ]);

        /** @var UpdateStatsIpMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpMessageHandler::class);
        $handler(new UpdateStatsIpMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip WHERE ip_address = ?::INET AND stat_date = CURRENT_DATE',
            [$ipAddress->getIpAddress()]
        );

        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(1, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
    }

    public function test_update_stats_ip_project(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable(),
        ]);
        $ipAddress = IpAddressFactory::createOne();

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'ip_address' => $ipAddress,
            'created_at' => new \DateTimeImmutable(),
        ]);

        /** @var UpdateStatsIpProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpProjectMessageHandler::class);
        $handler(new UpdateStatsIpProjectMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip_project WHERE ip_address = ?::INET AND project_id = ? AND stat_date = CURRENT_DATE',
            [$ipAddress->getIpAddress(), $project->getId()]
        );

        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['sent']);
    }

    public function test_update_stats_delivery_domain(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable(),
        ]);
        $ipAddress = IpAddressFactory::createOne();

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'ip_address' => $ipAddress,
            'domain' => 'example.com',
            'created_at' => new \DateTimeImmutable(),
        ]);

        /** @var UpdateStatsDeliveryDomainMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsDeliveryDomainMessageHandler::class);
        $handler(new UpdateStatsDeliveryDomainMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_delivery_domain WHERE project_id = ? AND ip_address = ?::INET AND recipient_domain = ? AND stat_date = CURRENT_DATE',
            [$project->getId(), $ipAddress->getIpAddress(), 'example.com']
        );

        $this->assertIsArray($row);
        $this->assertSame(1, (int)$row['sent']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertNull($row['provider']);
    }
}
