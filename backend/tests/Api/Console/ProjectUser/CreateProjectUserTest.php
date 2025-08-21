<?php

namespace App\Tests\Api\Console\ProjectUser;

use App\Api\Console\Controller\ProjectUserController;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\ProjectUser;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;
use Hyvor\Internal\Auth\AuthUser;

#[CoversClass(ProjectUserController::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(ProjectUserObject::class)]
class CreateProjectUserTest extends WebTestCase
{

    public function test_fails_when_user_not_found(): void
    {
        AuthFake::databaseAdd([
            'id' => 1,
            'username' => 'supun',
            'name' => 'Supun Wimalasena',
            'email' => 'supun@hyvor.com'
        ]);

        $project = ProjectFactory::createOne();

        $this->consoleApi(
            $project,
            'POST',
            '/project-users',
            [
                'user_id' => 999999,
                'scopes' => ['project.read'],
            ],
        );

        $this->assertResponseStatusCodeSame(404);
        $json = $this->getJson();
        $this->assertSame('User with id 999999 not found.', $json['message']);
    }

    public function test_creates_project_user(): void
    {
        $project = ProjectFactory::createOne();

        AuthFake::databaseAdd([
            'id' => 1,
            'username' => 'supun',
            'name' => 'Supun Wimalasena',
            'email' => 'supun@hyvor.com'
        ]);

        $this->consoleApi(
            $project,
            'POST',
            '/project-users',
            [
                'user_id' => 1,
                'scopes' => ['project.read', 'project.write'],
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('scopes', $json);
        $this->assertArrayHasKey('user', $json);

        $projectUserDb = $this->em->getRepository(ProjectUser::class)->find($json['id']);
        $this->assertInstanceOf(ProjectUser::class, $projectUserDb);
        $this->assertSame(1, $projectUserDb->getUserId());
    }
}
