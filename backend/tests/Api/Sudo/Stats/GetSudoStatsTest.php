<?php

namespace App\Tests\Api\Sudo\Stats;

use App\Api\Sudo\Controller\SudoStatsController;
use App\Service\Stats\SudoAnalyticsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SudoStatsController::class)]
#[CoversClass(SudoAnalyticsService::class)]
class GetSudoStatsTest extends WebTestCase
{
    public function test_gets_default_24h(): void
    {
        $project = ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('project_count', $json);
        $this->assertArrayHasKey('sends', $json);
        $this->assertArrayHasKey('bounce_rate', $json);
        $this->assertArrayHasKey('complaint_rate', $json);

        $this->assertSame(1, $json['project_count']);
        $this->assertSame(0, $json['sends']);
        $this->assertEquals(0.0, $json['bounce_rate']);
        $this->assertEquals(0.0, $json['complaint_rate']);
    }

    public function test_gets_7d(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
    }

    public function test_gets_30d(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats?period=30d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
    }

    public function test_invalid_period_fails(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats?period=invalid');
        $this->assertResponseStatusCodeSame(422);
    }

    public function test_calculates_bounce_and_complaint_rates(): void
    {
        $project = ProjectFactory::createOne();

        $this->em->getConnection()->executeStatement(
            <<<SQL
            INSERT INTO stats_project (
                project_id, stat_date,
                send_recipients, bounced_recipient, bounced_infrastructure, complained
            ) VALUES (
                :projectId, CURRENT_DATE,
                100, 5, 3, 2
            )
            SQL,
            ['projectId' => $project->getId()]
        );

        $this->sudoApi('GET', '/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
        $this->assertSame(100, $json['sends']);
        $this->assertEquals(0.08, $json['bounce_rate']);
        $this->assertEquals(0.02, $json['complaint_rate']);
    }

    public function test_aggregates_over_period(): void
    {
        $project = ProjectFactory::createOne();

        $this->em->getConnection()->executeStatement(
            <<<SQL
            INSERT INTO stats_project (
                project_id, stat_date,
                send_recipients, bounced_recipient, bounced_infrastructure, complained
            ) VALUES (
                :projectId, CURRENT_DATE - INTERVAL '1 day',
                50, 2, 1, 1
            )
            SQL,
            ['projectId' => $project->getId()]
        );

        $this->em->getConnection()->executeStatement(
            <<<SQL
            INSERT INTO stats_project (
                project_id, stat_date,
                send_recipients, bounced_recipient, bounced_infrastructure, complained
            ) VALUES (
                :projectId, CURRENT_DATE,
                50, 2, 1, 1
            )
            SQL,
            ['projectId' => $project->getId()]
        );

        $this->sudoApi('GET', '/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(100, $json['sends']);
        $this->assertEquals(0.06, $json['bounce_rate']);
        $this->assertEquals(0.02, $json['complaint_rate']);
    }
}
