<?php

namespace App\Tests\Api\Console\ProjectUser;

use App\Api\Console\Controller\ProjectUserController;
use App\Entity\ProjectUser;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectUserController::class)]
#[CoversClass(ProjectUserService::class)]
class DeleteAllProjectUserTest extends WebTestCase
{
    public function test_deletes_all_project_users(): void
    {
        $project = ProjectFactory::createOne();
        $otherProject = ProjectFactory::createOne();

        AuthFake::databaseSet([
            ['id' => 1, 'name' => 'John', 'email' => 'supun@test.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
            ['id' => 3, 'name' => 'Johnny', 'email' => 'supun@test.com']
        ]);

        $projectUser1 = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
            'scopes' => ['project.read'],
        ]);
        $projectUser2 = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 2,
            'scopes' => ['project.write'],
        ]);
        $otherProjectUser = ProjectUserFactory::createOne([
            'project' => $otherProject,
            'user_id' => 3,
            'scopes' => ['project.read'],
        ]);

        $this->assertInstanceOf(ProjectUser::class, $this->em->getRepository(ProjectUser::class)->find($projectUser1->getId()));
        $this->assertInstanceOf(ProjectUser::class, $this->em->getRepository(ProjectUser::class)->find($projectUser2->getId()));
        $this->assertInstanceOf(ProjectUser::class, $this->em->getRepository(ProjectUser::class)->find($otherProjectUser->getId()));

        $this->consoleApi(
            $project,
            'DELETE',
            '/project-users',
        );

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame([], $json);

        $this->assertNull($this->em->getRepository(ProjectUser::class)->find($projectUser1->getId()));
        $this->assertNull($this->em->getRepository(ProjectUser::class)->find($projectUser2->getId()));
        $this->assertInstanceOf(ProjectUser::class, $this->em->getRepository(ProjectUser::class)->find($otherProjectUser->getId()));
    }

    public function test_deletes_all_project_users_when_no_users_exist(): void
    {
        $project = ProjectFactory::createOne();

        // Ensure no project users exist for this project
        $existingUsers = $this->em->getRepository(ProjectUser::class)->findBy(['project' => $project->_real()]);
        $this->assertCount(0, $existingUsers);

        $this->consoleApi(
            $project,
            'DELETE',
            '/project-users',
        );

        $this->assertResponseStatusCodeSame(200);
        $json = $this->getJson();
        $this->assertSame([], $json);
    }
}
