<?php

namespace App\Tests\Api\Console\Analytics;

use App\Api\Console\Controller\AnalyticsController;
use App\Entity\Type\SendStatus;
use App\Service\Send\SendAnalyticsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AnalyticsController::class)]
#[CoversClass(SendAnalyticsService::class)]
class GetAnalyticsSendsChartTest extends WebTestCase
{

    public function test_gets_sends_chart_data(): void
    {
        $this->markTestSkipped();
        // TODO with new recipient statuses

//        Clock::set(new MockClock('2025-07-14'));
//
//        $project = ProjectFactory::createOne();
//
//        SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable('2025-07-01'), 'status' => SendStatus::ACCEPTED]);
//        SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable('2025-07-01'), 'status' => SendStatus::QUEUED]);
//
//        SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable('2025-07-03'), 'status' => SendStatus::ACCEPTED]);
//        SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable('2025-07-03'), 'status' => SendStatus::ACCEPTED]);
//        SendFactory::createOne(['project' => $project, 'created_at' => new \DateTimeImmutable('2025-07-03'), 'status' => SendStatus::BOUNCED]);
//
//        $this->consoleApi($project, 'GET', '/analytics/sends/chart');
//
//        $this->assertResponseStatusCodeSame(200);
//
//        /** @var array<array{date: string, accepted: int, bounced: int, queued: int}> $json */
//        $json = $this->getJson();
//
//        $this->assertSame('2025-06-14', $json[0]['date']);
//        $this->assertSame(0, $json[0]['accepted']);
//
//        $july1st = $json[17];
//        $this->assertSame('2025-07-01', $july1st['date']);
//        $this->assertSame(1, $july1st['accepted']);
//        $this->assertSame(0, $july1st['bounced']);
//        $this->assertSame(1, $july1st['queued']);
//
//        $july3rd = $json[19];
//        $this->assertSame('2025-07-03', $july3rd['date']);
//        $this->assertSame(2, $july3rd['accepted']);
//        $this->assertSame(1, $july3rd['bounced']);
//        $this->assertSame(0, $july3rd['queued']);

    }

}
