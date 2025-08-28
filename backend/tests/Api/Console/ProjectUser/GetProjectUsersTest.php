<?php

namespace App\Tests\Api\Console\ProjectUser;

use App\Api\Console\Controller\ProjectUserController;
use App\Api\Console\Object\ProjectUserObject;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectUserController::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(ProjectUserObject::class)]
class GetProjectUsersTest extends WebTestCase
{
    public function test_get_project_users(): void
    {
        AuthFake::databaseSet([
            ['id' => 1, 'name' => 'Nadil', 'email' => 'nadil@test.com'],
            ['id' => 2, 'name' => 'Supun', 'email' => 'supun@test.com'],
        ]);

        $project = ProjectFactory::createOne();
        $projectUsers1 = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1
        ]);
        $projectUsers2 = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 2
        ]);
        $projectUsers3 = ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 3
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/project-users'
        );

        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertSame(2, count($json));
        $this->assertSame('nadil@test.com', $json[0]['user']['email']);
        $this->assertSame('supun@test.com', $json[1]['user']['email']);
    }
}
