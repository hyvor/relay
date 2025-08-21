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
class DeleteProjectUserTest extends WebTestCase
{
    public function test_deletes_project_user(): void
    {
        $project = ProjectFactory::createOne();

        AuthFake::databaseAdd([
            'id' => 1,
            'username' => 'supun',
            'name' => 'Supun Wimalasena',
            'email' => 'supun@hyvor.com']);

        $projectUser = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
            'scopes' => ['project.read', 'project.write'],
        ]);

        $projectUserId = $projectUser->getId();

        $this->consoleApi(
            $project,
            'DELETE',
            '/project-users/' . $projectUser->getId(),
        );

        $this->assertResponseStatusCodeSame(200);

        $this->assertNull($this->em->getRepository(ProjectUser::class)->find($projectUserId));
    }

    public function test_fails_when_project_user_not_found(): void
    {

        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'DELETE',
            '/project-users/999999',
        );

        $this->assertResponseStatusCodeSame(404);
        $json = $this->getJson();
        $this->assertSame('Entity not found', $json['message']);
    }
}
