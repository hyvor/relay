<?php

namespace App\Tests\Api\Sudo\Stats;

use App\Api\Sudo\Controller\SudoStatsController;
use App\Entity\Project;
use App\Service\Stats\SudoAnalyticsService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;

#[CoversClass(SudoStatsController::class)]
#[CoversClass(SudoAnalyticsService::class)]
class GetSudoStatsTest extends WebTestCase
{
    private function insertProjectStats(
        Project $project,
        int $daysAgo,
        int $sendRecipients,
        int $accepted,
        int $bouncedRecipient,
        int $bouncedInfrastructure,
        int $complained,
    ): void {
        $this->em->getConnection()->executeStatement(
            <<<SQL
            INSERT INTO stats_project (
                project_id, stat_date,
                send_recipients, accepted, bounced_recipient, bounced_infrastructure, complained
            ) VALUES (
                :projectId, :statDate,
                :sendRecipients, :accepted, :bouncedRecipient, :bouncedInfrastructure, :complained
            )
            SQL,
            [
                'projectId' => $project->getId(),
                'statDate' => Clock::get()->now()->modify("-$daysAgo days")->format('Y-m-d'),
                'sendRecipients' => $sendRecipients,
                'accepted' => $accepted,
                'bouncedRecipient' => $bouncedRecipient,
                'bouncedInfrastructure' => $bouncedInfrastructure,
                'complained' => $complained,
            ]
        );
    }

    public function test_gets_default_24h(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('project_count', $json);
        $this->assertArrayHasKey('sends', $json);
        $this->assertArrayHasKey('bounce_rate', $json);
        $this->assertArrayHasKey('complaint_rate', $json);

        $this->assertSame(0, $json['project_count']);
        $this->assertSame(0, $json['sends']);
        $this->assertEquals(0.0, $json['bounce_rate']);
        $this->assertEquals(0.0, $json['complaint_rate']);
    }

    public function test_gets_7d(): void
    {
        $project = ProjectFactory::createOne();
        $this->insertProjectStats($project, 6, 10, 10, 0, 0, 0);
        $this->insertProjectStats($project, 7, 20, 20, 0, 0, 0);

        $this->sudoApi('GET', '/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
        $this->assertSame(10, $json['sends']);
    }

    public function test_gets_30d(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats?period=30d');
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_invalid_period_fails(): void
    {
        ProjectFactory::createOne();

        $this->sudoApi('GET', '/stats?period=invalid');
        $this->assertResponseStatusCodeSame(422);
    }

    public function test_24h_only_includes_today(): void
    {
        $project = ProjectFactory::createOne();
        $this->insertProjectStats($project, 0, 10, 10, 0, 0, 0);
        $this->insertProjectStats($project, 1, 20, 20, 0, 0, 0);

        $this->sudoApi('GET', '/stats?period=24h');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(10, $json['sends']);
    }

    public function test_project_count_only_includes_projects_active_in_period(): void
    {
        $activeProject = ProjectFactory::createOne();
        $inactiveProject = ProjectFactory::createOne();

        $this->insertProjectStats($activeProject, 0, 10, 10, 0, 0, 0);
        $this->insertProjectStats($activeProject, 1, 10, 10, 0, 0, 0);
        $this->insertProjectStats($inactiveProject, 7, 10, 10, 0, 0, 0);

        $this->sudoApi('GET', '/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(1, $this->getJson()['project_count']);
    }

    public function test_calculates_bounce_and_complaint_rates(): void
    {
        $project = ProjectFactory::createOne();
        $this->insertProjectStats($project, 0, 100, 80, 5, 3, 2);

        $this->sudoApi('GET', '/stats');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
        $this->assertSame(100, $json['sends']);
        $this->assertEquals(0.08, $json['bounce_rate']);
        $this->assertEquals(0.025, $json['complaint_rate']);
    }

    public function test_aggregates_over_period(): void
    {
        $project = ProjectFactory::createOne();
        $this->insertProjectStats($project, 1, 50, 40, 2, 1, 1);
        $this->insertProjectStats($project, 0, 50, 40, 2, 1, 1);

        $this->sudoApi('GET', '/stats?period=7d');
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(1, $json['project_count']);
        $this->assertSame(100, $json['sends']);
        $this->assertEquals(0.06, $json['bounce_rate']);
        $this->assertEquals(0.025, $json['complaint_rate']);
    }
}
