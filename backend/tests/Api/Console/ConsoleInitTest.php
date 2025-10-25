<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Controller\ConsoleController;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Service\ProjectUser\ProjectUserService;
use App\Service\Send\Compliance;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Sudo\SudoUserFactory;
use Hyvor\Internal\Sudo\SudoUserService;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(ConsoleController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(ProjectObject::class)]
#[CoversClass(Compliance::class)]
class ConsoleInitTest extends WebTestCase
{
    public function test_init_console(): void
    {
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        SudoUserFactory::createOne(['user_id' => 1]);

        ProjectUserFactory::createMany(5, [
            'user_id' => 1,
        ]);

        ProjectUserFactory::createMany(2, [
            'user_id' => 2,
        ]);

        $this->client->request(
            "GET",
            "/api/console/init",
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('project_users', $json);
        $this->assertArrayHasKey('config', $json);
        $this->assertIsArray($json['project_users']);
        $this->assertCount(5, $json['project_users']);
    }
}
