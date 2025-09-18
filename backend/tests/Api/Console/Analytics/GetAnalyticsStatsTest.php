<?php

namespace App\Tests\Api\Console\Analytics;

use App\Api\Console\Controller\AnalyticsController;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendStatus;
use App\Service\Send\SendAnalyticsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\SendFactory;
use App\Tests\Factory\SendRecipientFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AnalyticsController::class)]
#[CoversClass(SendAnalyticsService::class)]
class GetAnalyticsStatsTest extends WebTestCase
{

    public function test_gets_stats_default_30d(): void
    {
        $project = ProjectFactory::createOne();

        $send1 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-10 days')]);
        SendRecipientFactory::createOne(['send' => $send1, 'status' => SendRecipientStatus::ACCEPTED]);
        $send2 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-5 days')]);
        SendRecipientFactory::createOne(['send' => $send2, 'status' => SendRecipientStatus::BOUNCED]);
        $send3 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-2 days')]);
        SendRecipientFactory::createOne(['send' => $send3, 'status' => SendRecipientStatus::COMPLAINED]);
        $send4 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-1 day')]);
        SendRecipientFactory::createOne(['send' => $send4, 'status' => SendRecipientStatus::QUEUED]);

        // too old
        $send5 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-35 day')]);
        SendRecipientFactory::createOne(['send' => $send5, 'status' => SendRecipientStatus::ACCEPTED]);

        $this->consoleApi($project, 'GET', '/analytics/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(4, $json['sends_30d']);
        $this->assertSame(0.25, $json['bounce_rate_30d']);
        $this->assertSame(0.25, $json['complaint_rate_30d']);
    }

    public function test_gets_stats_7d(): void
    {
        $project = ProjectFactory::createOne();

        $send1 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-2 days')]);
        SendRecipientFactory::createOne(['send' => $send1, 'status' => SendRecipientStatus::ACCEPTED]);
        $send2 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-1 day')]);
        SendRecipientFactory::createOne(['send' => $send2, 'status' => SendRecipientStatus::BOUNCED]);

        // too old for 7d period
        $send3 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-10 days')]);
        SendRecipientFactory::createOne(['send' => $send3, 'status' => SendRecipientStatus::COMPLAINED]);

        $this->consoleApi($project, 'GET', '/analytics/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(2, $json['sends_7d']);
        $this->assertSame(0.5, $json['bounce_rate_7d']);
        $this->assertSame(0, $json['complaint_rate_7d']);
    }

    public function test_gets_stats_24h(): void
    {
        $project = ProjectFactory::createOne();

        $send1 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-2 hours')]);
        SendRecipientFactory::createOne(['send' => $send1, 'status' => SendRecipientStatus::ACCEPTED]);

        // too old for 24h period
        $send2 = SendFactory::createOne(['project' => $project, 'created_at' => new \DateTime('-2 days')]);
        SendRecipientFactory::createOne(['send' => $send2, 'status' => SendRecipientStatus::BOUNCED]);

        $this->consoleApi($project, 'GET', '/analytics/stats?period=24h');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['sends_24h']);
        $this->assertSame(0, $json['bounce_rate_24h']);
        $this->assertSame(0, $json['complaint_rate_24h']);
    }

    public function test_invalid_period_fails(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi($project, 'GET', '/analytics/stats?period=invalid');
        $this->assertResponseStatusCodeSame(422);
    }

}
