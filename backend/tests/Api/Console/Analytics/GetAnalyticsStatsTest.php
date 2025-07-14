<?php

namespace App\Tests\Api\Console\Analytics;

use App\Api\Console\Controller\AnalyticsController;
use App\Entity\Type\SendStatus;
use App\Service\Send\SendAnalyticsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AnalyticsController::class)]
#[CoversClass(SendAnalyticsService::class)]
class GetAnalyticsStatsTest extends WebTestCase
{

    public function test_gets_stats(): void
    {

        $project = ProjectFactory::createOne();

        $send1 = SendFactory::createOne(['project' => $project, 'status' => SendStatus::ACCEPTED, 'created_at' => new \DateTime('-10 days')]);
        $send2 = SendFactory::createOne(['project' => $project, 'status' => SendStatus::BOUNCED, 'created_at' => new \DateTime('-5 days')]);
        $send3 = SendFactory::createOne(['project' => $project, 'status' => SendStatus::COMPLAINED, 'created_at' => new \DateTime('-2 days')]);
        $send4 = SendFactory::createOne(['project' => $project, 'status' => SendStatus::QUEUED, 'created_at' => new \DateTime('-1 day')]);

        // too old
        $send5 = SendFactory::createOne(['project' => $project, 'status' => SendStatus::ACCEPTED, 'created_at' => new \DateTime('-35 day')]);

        $this->consoleApi($project, 'GET', '/analytics/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(4, $json['sends_30d']);
        $this->assertSame(0.25, $json['bounce_rate_30d']);
        $this->assertSame(0.25, $json['complaint_rate_30d']);

    }

}