<?php

namespace App\Tests\Service\Stats\MessageHandler;

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
class UpdateStatsIpProjectHandlerTest extends WebTestCase
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

        /** @var UpdateStatsIpProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpProjectMessageHandler::class);
        $handler(new UpdateStatsIpProjectMessage());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip_project WHERE ip_address_id = ? AND project_id = ? AND stat_date = CURRENT_DATE',
            [$ipAddress->getId(), $project->getId()]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sent']);
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

        /** @var UpdateStatsIpProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsIpProjectMessageHandler::class);
        $handler(new UpdateStatsIpProjectMessage(true));

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_ip_project WHERE ip_address_id = ? AND project_id = ? AND stat_date = CURRENT_DATE - 1',
            [$ipAddress->getId(), $project->getId()]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sent']);
    }
}