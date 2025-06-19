<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Controller\ConsoleController;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConsoleController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class ConsoleInitTest extends WebTestCase
{
    public function test_init_console(): void
    {
        $projects = ProjectFactory::createMany(5, [
            'hyvor_user_id' => 1,
        ]);

        $otherProjects = ProjectFactory::createMany(2, [
            'hyvor_user_id' => 2,
        ]);

        $response = $this->consoleApi(
            null,
            'GET',
            '/init'
        );

        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertArrayHasKey('projects', $json);
        $this->assertCount(5, $json['projects']);
        $this->assertArrayHasKey('id', $json['projects'][0]);
    }
}