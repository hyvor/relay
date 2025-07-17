<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Controller\ConsoleController;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(ConsoleController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class ConsoleInitTest extends WebTestCase
{
    public function test_init_console(): void
    {
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));

        $projects = ProjectFactory::createMany(5, [
            'hyvor_user_id' => 1,
        ]);

        $otherProjects = ProjectFactory::createMany(2, [
            'hyvor_user_id' => 2,
        ]);


        $this->client->request(
            "GET",
            "/api/console/init",
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('projects', $json);
        $this->assertArrayHasKey('config', $json);
        $this->assertIsArray($json['projects']);
        $this->assertCount(5, $json['projects']);
    }
}
