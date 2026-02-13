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
use Hyvor\Internal\Bundle\Comms\Comms;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\VerifyMember;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\VerifyMemberResponse;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use PHPUnit\Framework\Attributes\CoversClass;

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

		$project = ProjectFactory::createOne([
			'organization_id' => 1
		]);

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
        $this->getComms()->addResponse(VerifyMember::class, function () {
            return new VerifyMemberResponse(true, 'admin');
        });

		$project = ProjectFactory::createOne([
			'organization_id' => 1
		]);

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
        $this->assertArrayHasKey('created_at', $json);
        $this->assertArrayHasKey('scopes', $json);
        $this->assertArrayHasKey('user', $json);
        $this->assertArrayHasKey('oidc_sub', $json);

        $projectUserDb = $this->em->getRepository(ProjectUser::class)->find($json['id']);
        $this->assertInstanceOf(ProjectUser::class, $projectUserDb);
        $this->assertSame(1, $projectUserDb->getUserId());
    }

    public function test_fails_when_user_already_added(): void
    {
        $this->getComms()->addResponse(VerifyMember::class, function () {
            return new VerifyMemberResponse(true, 'admin');
        });

        $project = ProjectFactory::createOne([
            'organization_id' => 1
        ]);
        ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
        ]);

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

        $this->assertResponseFailed(400, "User is already added to the project");
    }

    public function test_fails_when_user_not_in_org(): void
    {
        $this->getComms()->addResponse(VerifyMember::class, function () {
            return new VerifyMemberResponse(false, null);
        });

        $project = ProjectFactory::createOne([
            'organization_id' => 1
        ]);

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

        $this->assertResponseFailed(400, "Unable to find the user in the organization");
    }

    public function test_fails_on_comms_api_faliure(): void
    {
        $this->getComms()->addResponse(VerifyMember::class, function () {
            throw new CommsApiFailedException();
        });

        $project = ProjectFactory::createOne([
            'organization_id' => 1
        ]);

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

        $this->assertResponseFailed(400, "Unable to verify the user.");
    }
}
