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

    public function test_gets_stats(): void
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

}
