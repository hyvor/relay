<?php

namespace App\Tests\Service\Stats\MessageHandler;

use App\Entity\Project;
use App\Entity\Send;
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
class UpdateStatsProjectMessageHandlerTest extends WebTestCase
{
    private function createSendWithRecipients(Project $project, \DateTimeImmutable $createdAt): Send
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
            'bounced_reason' => BounceReason::RECIPIENT,
            'address' => 'b@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
            'bounced_reason' => BounceReason::INFRASTRUCTURE,
            'address' => 'c@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::BOUNCED,
            'bounced_reason' => BounceReason::UNKNOWN,
            'address' => 'd@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::COMPLAINED,
            'address' => 'e@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::SUPPRESSED,
            'address' => 'f@example.com',
        ]);
        SendRecipientFactory::createOne([
            'send' => $send,
            'status' => SendRecipientStatus::FAILED,
            'address' => 'g@example.com',
        ]);

        SendAttemptFactory::createOne([
            'send' => $send,
            'status' => SendAttemptStatus::ACCEPTED,
            'created_at' => $createdAt,
        ]);

        return $send;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function assertAndGetRow(int $projectId, string $stat_date_sql): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT * FROM stats_project WHERE project_id = ? AND stat_date = $stat_date_sql",
            [$projectId]
        );

        $this->assertIsArray($row);
        /** @var array<string, int|string|null> $row */
        return $row;
    }

    public function test_updates_todays_stats(): void
    {
        $project = ProjectFactory::createOne();
        $this->createSendWithRecipients($project, new \DateTimeImmutable('today'));

        /** @var UpdateStatsProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsProjectMessageHandler::class);
        $handler(new UpdateStatsProjectMessage());

        $row = $this->assertAndGetRow($project->getId(), 'CURRENT_DATE');

        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(7, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['send_attempts']);
        $this->assertSame(1, (int)$row['accepted']);
        $this->assertSame(0, (int)$row['deferred']);
        $this->assertSame(1, (int)$row['bounced_recipient']);
        $this->assertSame(1, (int)$row['bounced_infrastructure']);
        $this->assertSame(1, (int)$row['bounced_unknown']);
        $this->assertSame(1, (int)$row['complained']);
        $this->assertSame(1, (int)$row['suppressed']);
        $this->assertSame(1, (int)$row['failed']);
        $this->assertGreaterThan(0, (float)$row['accepted_rate']);
        $this->assertGreaterThan(0, (float)$row['bounced_unknown_rate']);
    }

    public function test_updates_last_days_stats(): void
    {
        $project = ProjectFactory::createOne();
        $this->createSendWithRecipients($project, new \DateTimeImmutable('yesterday'));

        // a send made today should not affect yesterday's rollup
        $this->createSendWithRecipients($project, new \DateTimeImmutable('today'));

        /** @var UpdateStatsProjectMessageHandler $handler */
        $handler = self::getContainer()->get(UpdateStatsProjectMessageHandler::class);
        $handler(new UpdateStatsProjectMessage(forLastDay: true));

        $row = $this->assertAndGetRow($project->getId(), "CURRENT_DATE - INTERVAL '1 day'");

        $this->assertSame(1, (int)$row['sends']);
        $this->assertSame(7, (int)$row['send_recipients']);
        $this->assertSame(1, (int)$row['bounced_recipient']);
        $this->assertSame(1, (int)$row['bounced_infrastructure']);
        $this->assertSame(1, (int)$row['bounced_unknown']);

        $todayRow = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM stats_project WHERE project_id = ? AND stat_date = CURRENT_DATE',
            [$project->getId()]
        );
        $this->assertFalse($todayRow, 'today\'s rollup should not run when forLastDay is true');
    }
}
