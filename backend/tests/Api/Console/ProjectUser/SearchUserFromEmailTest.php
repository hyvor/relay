<?php

namespace App\Tests\Api\Console\ProjectUser;

use App\Api\Console\Controller\ProjectUserController;
use App\Api\Console\Object\ProjectUserSearchObject;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectUserController::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(ProjectUserSearchObject::class)]
class SearchUserFromEmailTest extends WebTestCase
{
    public function test_search_user_from_email(): void
    {
        AuthFake::databaseAdd([
            'id' => 1,
            'username' => 'supun',
            'name' => 'Supun Wimalasena',
            'email' => 'supun@hyvor.com'
        ]);

        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/search-users?email=supun@hyvor.com',
        );

        $this->assertResponseStatusCodeSame(200);

        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(1, $json);
        $user = $json[0];
        $this->assertIsArray($user);
        $this->assertSame(1, $user['id']);
        $this->assertSame('supun@hyvor.com', $user['email']);
    }

    public function test_search_user_from_email_multiple_hit(): void
    {
        AuthFake::databaseSet([
            ['id' => 1, 'name' => 'John', 'email' => 'supun@test.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
            ['id' => 3, 'name' => 'Johnny', 'email' => 'supun@test.com']
        ]);

        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/search-users?email=supun@test.com',
        );

        $this->assertResponseStatusCodeSame(200);

        /** @var array<array<string, mixed>> $json */
        $json = $this->getJson();

        $this->assertCount(2, $json);
    }

    public function test_search_user_from_email_not_found(): void
    {
        AuthFake::databaseAdd([
            'id' => 1,
            'username' => 'supun',
            'name' => 'Supun Wimalasena',
            'email' => 'supun@hyvor.com'
        ]);

        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/search-users?email=thibault@hyvor.com'
        );

        $this->assertResponseStatusCodeSame(200);
        /*
         * @var array<int, array<string, mixed>> $content
         */
        $content = $this->getJson();

        $this->assertCount(0, $content);
    }
}
