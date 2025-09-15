<?php

namespace App\Tests\Api\Console\Project;

use App\Api\Console\Controller\ProjectController;
use App\Api\Console\Object\ProjectObject;
use App\Entity\Project;
use App\Entity\Type\ProjectSendType;
use App\Service\Project\ProjectService;
use App\Tests\Case\WebTestCase;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(ProjectController::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectObject::class)]
class CreateProjectTest extends WebTestCase
{
    protected function shouldEnableAuthFake(): bool
    {
        return false;
    }

    public function test_create_project_valid(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));

        $this->client->request(
            "POST",
            "/api/console/project",
            [
                'name' => 'Valid Project Name',
                'send_type' => 'transactional',
            ],
        );

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('project', $json);
        $this->assertArrayHasKey('scopes', $json);
        $project = $json['project'];
        $scopes = $json['scopes'];

        $this->assertIsArray($project);
        $this->assertIsArray($scopes);

        $this->assertArrayHasKey('id', $project);
        $this->assertArrayHasKey('created_at', $project);
        $this->assertArrayHasKey('name', $project);
        $this->assertSame(13, count($scopes));

        $projectDb = $this->em->getRepository(Project::class)->find($project['id']);
        $this->assertNotNull($projectDb);
        $this->assertSame('Valid Project Name', $projectDb->getName());
        $this->assertSame(ProjectSendType::TRANSACTIONAL, $projectDb->getSendType());
    }

    public function test_disallow_project_creation_for_non_sudo_users(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 99]);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));

        $this->client->request(
            "POST",
            "/api/console/project",
            [
                'name' => 'Valid Project Name',
                'send_type' => 'transactional',
            ],
        );

        $this->assertResponseStatusCodeSame(400);

        $json = $this->getJson();
        $this->assertArrayHasKey('message', $json);
        $this->assertSame('Currently not available for public usage.', $json['message']);
    }
}
