<?php

namespace App\Tests\Service\Stats\MessageHandler;

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
class UpdateStatsIpHandlerTest extends WebTestCase
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
            'created_at' => new \DateTimeImmutable(),
        ]);

        /** @var UpdateStatsIpMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpMessageHandler::class);
        $handler(new UpdateStatsIpMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip WHERE ip_address_id = ? AND stat_date = CURRENT_DATE',
            [$ipAddress->getId()]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(1, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
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
            'created_at' => new \DateTimeImmutable('yesterday'),
        ]);

        /** @var UpdateStatsIpMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpMessageHandler::class);
        $handler(new UpdateStatsIpMessage(true));

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip WHERE ip_address_id = ? AND stat_date = CURRENT_DATE - 1',
            [$ipAddress->getId()]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(1, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
    }
}