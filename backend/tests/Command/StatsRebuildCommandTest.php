<?php

namespace App\Tests\Command;

use App\Command\Stats\StatsRebuildCommand;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\MessageHandler\UpdateStatsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StatsRebuildCommand::class)]
class StatsRebuildCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Clock::set(new MockClock('2026-06-15 12:00:00'));
    }

    public function test_rebuilds_a_range_of_dates(): void
    {
        $project = ProjectFactory::createOne();
        foreach (['2026-06-10', '2026-06-11'] as $day) {
            $send = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable($day . ' 10:00:00')]);
            SendRecipientFactory::createOne(['send' => $send, 'status' => SendRecipientStatus::ACCEPTED]);
        }

        $tester = $this->commandTester('stats:rebuild');
        $tester->execute(['from' => '2026-06-10', 'to' => '2026-06-11']);
        $tester->assertCommandIsSuccessful();

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT stat_date, accepted FROM stats_project ORDER BY stat_date'
        );
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['accepted']);
        $this->assertSame(1, $rows[1]['accepted']);
    }

    public function test_rejects_dates_past_retention(): void
    {
        $old = Clock::get()->now()->modify('-' . (UpdateStatsMessageHandler::MAX_REBUILD_AGE_DAYS + 1) . ' days')->format('Y-m-d');

        $tester = $this->commandTester('stats:rebuild');
        $this->assertSame(2, $tester->execute(['from' => $old]));
        $this->assertStringContainsString('can not be re-counted', $tester->getDisplay());
    }

    public function test_rejects_invalid_dates(): void
    {
        $tester = $this->commandTester('stats:rebuild');
        $this->assertSame(2, $tester->execute(['from' => 'nope']));
        $this->assertSame(2, $tester->execute(['from' => '2026-06-12', 'to' => '2026-06-11']));
    }
}
