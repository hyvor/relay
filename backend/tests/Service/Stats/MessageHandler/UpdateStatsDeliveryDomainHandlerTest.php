<?php

namespace App\Tests\Service\Stats\MessageHandler;

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
class UpdateStatsDeliveryDomainHandlerTest extends WebTestCase
{
    public function test_updates_today(): void
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
            'SELECT * FROM stats_delivery_domain WHERE project_id = ? AND ip_address_id = ? AND recipient_domain = ? AND stat_date = CURRENT_DATE',
            [$project->getId(), $ipAddress->getId(), 'example.com']
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sent']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertNull($row['provider']);
    }

    public function test_updates_last_day(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable('yesterday'),
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
            'created_at' => new \DateTimeImmutable('yesterday'),
        ]);

        /** @var UpdateStatsDeliveryDomainMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsDeliveryDomainMessageHandler::class);
        $handler(new UpdateStatsDeliveryDomainMessage(true));

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_delivery_domain WHERE project_id = ? AND ip_address_id = ? AND recipient_domain = ? AND stat_date = CURRENT_DATE - 1',
            [$project->getId(), $ipAddress->getId(), 'example.com']
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sent']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertNull($row['provider']);
    }
}