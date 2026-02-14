<?php

namespace App\Tests\Command;

use App\Entity\Project;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\EnsureMembers;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrg;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\OrgMigration\InitOrgResponse;
use Hyvor\Internal\Component\Component;
use PHPUnit\Framework\Attributes\CoversClass;
use App\Command\OrganizationsMigrateCommand;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(OrganizationsMigrateCommand::class)]
class OrganizationsMigrateCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

	public function test_organization_migration(): void
    {
        $this->mockTime();

        $projects = ProjectFactory::createMany(3, [
            'organization_id' => 0,
        ]);

        $this->getComms()->addResponse(InitOrg::class, function () {
            return new InitOrgResponse(rand(1000, 9999));
        });

        $expectedEvents = [];
        foreach ($projects as $project) {
            $user1 = ProjectUserFactory::createOne(['project' => $project, 'user_id' => $project->getUserId()]);
            $user2 = ProjectUserFactory::createOne(['project' => $project]);

            $ids = [$user1->getUserId(), $user2->getUserId()];
            sort($ids);

            $expectedEvents[] = [
                'projectId' => $project->getId(),
                'userIds' => $ids
            ];
        }

        $this->assertSame(0, $this->commandTester('organizations:migrate')->execute([]));
        $this->getEm()->clear();

		$this->getComms()->assertSent(InitOrg::class, Component::CORE);
        $this->getComms()->assertSent(
            EnsureMembers::class,
            Component::CORE,
            eventValidator: function($sent) use ($expectedEvents) {
                $userIds = $sent->userIds;
                sort($userIds);

                foreach ($expectedEvents as $expected) {
					$project = $this->getEm()->find(Project::class, $expected['projectId']);
					$this->assertNotNull($project);
                    if ($sent->orgId === $project->getOrganizationId() && $userIds === $expected['userIds']) {
						return;
                    }
				}

				// @phpstan-ignore-next-line
				$this->assertTrue(false, "should not be reachable");
            }
        );

        $updatedProjects = $this->getEm()->getRepository(Project::class)->findBy([
            'organization_id' => 0,
        ]);
        $this->assertCount(0, $updatedProjects);
    }

    public function test_updates_organization_id(): void
    {
        $this->mockTime();

        $project = ProjectFactory::createOne([
            'organization_id' => 0,
        ]);

        $orgId = rand(1000, 9999);
        $this->getComms()->addResponse(InitOrg::class, function () use ($orgId) {
            return new InitOrgResponse($orgId);
        });

        ProjectUserFactory::createOne(['project' => $project, 'user_id' => $project->getUserId()]);

        $this->assertSame(0, $this->commandTester('organizations:migrate')->execute([]));
        $this->getEm()->clear();

        $this->getComms()->assertSent(InitOrg::class, Component::CORE);
        $this->getComms()->assertSent(EnsureMembers::class, Component::CORE);

        $updatedProject = $this->getEm()->getRepository(Project::class)->findOneBy([
            'organization_id' => $orgId,
        ]);
        $this->assertNotNull($updatedProject);
    }

    public function test_does_not_update_migrated_organizations(): void
	{
        $this->mockTime();

        $projects = ProjectFactory::createMany(3, [
            'organization_id' => 0,
        ]);

        $this->getComms()->addResponse(InitOrg::class, function () {
            return new InitOrgResponse(1234);
        });

        foreach ($projects as $project) {
            ProjectUserFactory::createOne([
                'project' => $project,
                'user_id' => $project->getUserId(),
			]);

            ProjectUserFactory::createOne([
                'project' => $project,
			]);
        }

        $migratedProjects = ProjectFactory::createMany(2, [
            'organization_id' => 4321
		]);

        foreach ($migratedProjects as $project) {
            ProjectUserFactory::createOne([
                'project' => $project,
                'user_id' => $project->getUserId(),
			]);

            ProjectUserFactory::createOne([
                'project' => $project,
			]);
        }

        $this->assertSame(0, $this->commandTester('organizations:migrate')->execute([]));
        $this->getEm()->clear();

        $availProjects = $this->getEm()->getRepository(Project::class)->findBy([
            'organization_id' => 4321,
		]);

		$this->assertCount(2, $availProjects);

        $availMigratedProjects = $this->getEm()->getRepository(Project::class)->findBy([
            'organization_id' => 1234,
		]);

		$this->assertCount(3, $availMigratedProjects);
	}

	public function test_skips_system_projects(): void
    {
        $this->mockTime();

        $systemProject = ProjectFactory::createOne([
            'organization_id' => 0,
            'user_id' => 0,
        ]);

        $normalProject = ProjectFactory::createOne([
            'organization_id' => 0,
            'user_id' => 123,
        ]);

        $this->getComms()->addResponse(InitOrg::class, function () {
            return new InitOrgResponse(9999);
        });

        ProjectUserFactory::createOne(['project' => $systemProject, 'user_id' => 0]);
        ProjectUserFactory::createOne(['project' => $normalProject, 'user_id' => 123]);

        $this->assertSame(0, $this->commandTester('organizations:migrate')->execute([]));
        $this->getEm()->clear();

        $unchangedSystemProject = $this->getEm()->getRepository(Project::class)->findOneBy([
            'id' => $systemProject->getId(),
            'organization_id' => 0
        ]);
        $this->assertNotNull($unchangedSystemProject, 'System project should not have been migrated.');

        $changedNormalProject = $this->getEm()->getRepository(Project::class)->findOneBy([
            'id' => $normalProject->getId(),
            'organization_id' => 9999
        ]);
        $this->assertNotNull($changedNormalProject, 'Normal project should have been migrated.');

        $sentEvents = $this->getComms()->getSents();

        $initOrgEvents = array_filter($sentEvents, function ($sent) {
            return $sent['event'] instanceof InitOrg;
        });

        $this->assertCount(1, $initOrgEvents, 'System project should be skipped');
    }
}
