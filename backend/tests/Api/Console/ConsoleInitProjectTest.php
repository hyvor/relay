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
class ConsoleInitProjectTest extends WebTestCase
{
    public function test_init_project(): void
    {
        $project = ProjectFactory::createOne([
            'hyvor_user_id' => 1,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/init/project'
        );

        $this->assertSame(200, $response->getStatusCode());

        $json = $this->getJson();
        $this->assertArrayHasKey('project', $json);
        $this->assertArrayHasKey('id', $json['project']);
        $this->assertArrayHasKey('created_at', $json['project']);
        $this->assertArrayHasKey('name', $json['project']);
        $this->assertSame($project->getId(), $json['project']['id']);
        $this->assertSame($project->getName(), $json['project']['name']);
    }
}