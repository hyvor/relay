<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Controller\ProjectController;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class CreateProjectTest extends WebTestCase
{
    public function test_create_project_valid(): void
    {

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));

        $this->client->request(
            "POST",
            "/api/console/project",
            [
                'name' => 'Valid Project Name'
            ],
            useSession: true
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('created_at', $json);
        $this->assertArrayHasKey('name', $json);
    }
}
