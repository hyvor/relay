<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\Scope;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;

#[CoversClass(AuthorizationListener::class)]
class AuthorizationTest extends WebTestCase
{

    protected function shouldEnableAuthFake(): bool
    {
        return false;
    }

    public function test_api_key_authentication_nothing(): void
    {
        $this->client->request("GET", "/api/console/sends");
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            "Authorization method not supported. Use either Bearer token or a session.",
            $this->getJson()["message"]
        );
    }

    public function test_wrong_authorization_header(): void
    {
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_AUTHORIZATION" => "WrongHeader",
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            'Authorization header must start with "Bearer ".',
            $this->getJson()["message"]
        );
    }

    public function test_missing_bearer_token(): void
    {
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_AUTHORIZATION" => "Bearer ",
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            "API key is missing or empty.",
            $this->getJson()["message"]
        );
    }

    public function test_invalid_api_key(): void
    {
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_AUTHORIZATION" => "Bearer InvalidApiKey",
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Invalid API key.", $this->getJson()["message"]);
    }

    public function test_invalid_project_id(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_PROJECT_ID" => "999",
            ],
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Invalid project ID.", $this->getJson()["message"]);
    }

    public function test_invalid_session(): void
    {
        AuthFake::enableForSymfony($this->container, null);

        $project = ProjectFactory::createOne();

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_PROJECT_ID" => $project->getId(),
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Invalid session.", $this->getJson()["message"]);
    }

    public function test_fails_when_xprojectid_header_is_not_set(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("X-Project-ID is required for this endpoint.", $this->getJson()["message"]);
    }

    public function test_user_not_authorized_for_project(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        $project = ProjectFactory::createOne();
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_PROJECT_ID" => $project->getId(),
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            "You do not have access to this project.",
            $this->getJson()["message"]
        );
    }

    public function test_missing_scope_required_attribute(): void
    {
        $project = ProjectFactory::createOne();
        $this->consoleApi(
            $project,
            'GET',
            '/sends',
            scopes: [Scope::SENDS_WRITE]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(
            "You do not have the required scope 'sends.read' to access this resource.",
            $this->getJson()["message"]
        );
    }

    public function test_authorizes_via_api_key(): void
    {

        $project = ProjectFactory::createOne();
        $this->consoleApi(
            $project,
            'GET',
            '/sends',
            scopes: [Scope::SENDS_READ]
        );
        $this->assertResponseStatusCodeSame(200);

        $projectFromAttr = $this->client->getRequest()->attributes->get('console_api_resolved_project');
        $this->assertInstanceOf(
            \App\Entity\Project::class,
            $projectFromAttr
        );
        $this->assertSame($project->getId(), $projectFromAttr->getId());

    }

    public function test_authorizes_via_session(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        $project = ProjectFactory::createOne([
            'hyvor_user_id' => 1
        ]);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_PROJECT_ID" => $project->getId(),
            ]
        );
        $this->assertResponseStatusCodeSame(200);

        $projectFromAttr = $this->client->getRequest()->attributes->get('console_api_resolved_project');
        $this->assertInstanceOf(
            \App\Entity\Project::class,
            $projectFromAttr
        );
        $this->assertSame($project->getId(), $projectFromAttr->getId());

        $userFromAttr = $this->client->getRequest()->attributes->get('console_api_resolved_user');
        $this->assertInstanceOf(AuthUser::class, $userFromAttr);
        $this->assertSame(1, $userFromAttr->id);
    }

    public function test_user_level_endpoint_works(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/init",
        );
        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertArrayHasKey('projects', $json);
        $this->assertArrayHasKey('config', $json);

    }

}
