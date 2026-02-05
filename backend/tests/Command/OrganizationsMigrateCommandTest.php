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

#[CoversClass(OrganizationsMigrateCommand::class)]
class OrganizationsMigrateCommandTest extends KernelTestCase
{
    public function test_organization_migration(): void
    {
        $projects = ProjectFactory::createMany(3, [
            'organization_id' => null,
        ]);

        $this->getComms()->addResponse(InitOrg::class, function () {
            return new InitOrgResponse(rand());
        });

		$expectedEvents = [];

        foreach ($projects as $project) {
            $user1 = ProjectUserFactory::createOne([
                'project' => $project,
                'user_id' => $project->getUserId(),
			]);

            $user2 = ProjectUserFactory::createOne([
                'project' => $project,
			]);

			$expectedEvents[] = [
				'project' => $project,
				'userIds' =>[$user1->getUserId(), $user2->getUserId()]
			];
        }

        $this->assertSame(0, $this->commandTester('organizations:migrate')->execute([]));

        $this->getComms()->assertSent(InitOrg::class, Component::CORE);
		$this->getComms()->assertSent(
			EnsureMembers::class,
			Component::CORE,
			eventValidator: function($sent) use ($expectedEvents) {
				$this->assertTrue(array_any(
					$expectedEvents,
					fn($expected) =>
						$sent->orgId === $expected['project']->getOrganizationId()
						&& $sent->userIds == $expected['userIds']
				));
			}
		);

        $pendingProjects = $this->getEm()->getRepository(Project::class)->findBy([
            'organization_id' => null,
		]);

        $this->assertCount(0, $pendingProjects);

    }

    public function test_does_not_update_migrated_organizations(): void
	{
        $projects = ProjectFactory::createMany(3, [
            'organization_id' => null,
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

        $availProjects = $this->getEm()->getRepository(Project::class)->findBy([
            'organization_id' => 4321,
		]);

        $this->assertCount(2, $availProjects);
    }
}
