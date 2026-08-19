<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\Message\UpdateStatsProjectMessage;
use App\Service\Stats\MessageHandler\UpdateStatsProjectMessageHandler;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendAttemptFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateStatsProjectMessageHandler::class)]
class UpdateStatsProjectHandlerTest extends WebTestCase
{
    public function test_updates_today(): void
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
        /** @var array<string, int|string|null> $row */
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

    public function test_updates_last_day(): void
    {
        $project = ProjectFactory::createOne();
        $send = SendFactory::createOne([
            'project' => $project,
            'created_at' => new \DateTimeImmutable('yesterday'),
        ]);

        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::ACCEPTED,
            'address' => 'a@example.com',
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'created_at' => new \DateTimeImmutable('yesterday'),
        ]);

        /** @var UpdateStatsProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsProjectMessageHandler::class);
        $handler(new UpdateStatsProjectMessage(true));

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_project WHERE project_id = ? AND stat_date = CURRENT_DATE - 1',
            [$project->getId()]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(1, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertSame(0, (int)$row['deferred']);
        $this->assertSame(0, (int)$row['bounced_recipient']);
        $this->assertSame(0, (int)$row['bounced_infrastructure']);
        $this->assertSame(0, (int)$row['complained']);
        $this->assertSame(0, (int)$row['suppressed']);
        $this->assertSame(0, (int)$row['failed']);
        $this->assertGreaterThan(0, (float)$row['accepted_rate']);
    }
}