<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Authorization\AuthorizationListener;
use Hyvor\Internal\CloudApi\Scope\RelayScope;
use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\AllowedIp;
use App\Service\ApiKey\ApiKeyService;
use App\Service\Project\ProjectService;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ApiKeyFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Auth\AuthUserOrganization;
use Hyvor\Internal\CloudApi\CloudApiService;
use Hyvor\Internal\CloudApi\CloudJwt;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\AccessType;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleApiAuthorizationListenerAbstract;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\Sudo\SudoUserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AuthorizationListener::class)]
#[CoversClass(ProjectService::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(AllowedIp::class)]
class AuthorizationTest extends WebTestCase
{

    protected function shouldEnableAuthFake(): bool
    {
        return false;
    }

    public function test_fails_when_xprojectid_header_is_not_set(): void
    {
        AuthFake::enableForSymfony(
            $this->container,
            ['id' => 1],
            new AuthUserOrganization(
                id: 1,
                name: 'Fake Organization',
                role: 'member'
            )
        );

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_ORGANIZATION_ID" => "1",
            ],
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Unable to find the project from the request. Please provide a valid X-Project-ID header.", $this->getJson()["message"]);
    }

    public function test_authorizes_via_api_key_and_updates_last_usage(): void
    {
        Clock::set(new MockClock('2025-06-01 00:00:00'));

        $project = ProjectFactory::createOne();
        $this->consoleApi(
            $project,
            'GET',
            '/sends',
            scopes: [RelayScope::SENDS_READ]
        );
        $this->assertResponseStatusCodeSame(200);

        $authResults = $this->client->getRequest()->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);
        $this->assertInstanceOf(ConsoleAuthResults::class, $authResults);
        $resource = $authResults->getResource();
        $this->assertInstanceOf(Project::class, $resource);
        $this->assertSame($project->getId(), $resource->getId());

        $apiKey = $this->em->getRepository(ApiKey::class)->findOneBy(['project' => $project]);

        $this->assertInstanceOf(ApiKey::class, $apiKey);
        $this->assertSame(
            '2025-06-01 00:00:00',
            $apiKey->getLastAccessedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param string[] $allowedIps
     */
    #[TestWith([['203.0.113.5', '198.51.100.0/24'], '198.51.100.42'])]
    #[TestWith([['2001:db8::1', '2001:db8::/32'], '2001:db8::1234'])]
    public function test_api_key_with_allowed_ips_accepts_matching_ip(
        array $allowedIps,
        string $clientIp
    ): void {
        $project = ProjectFactory::createOne();
        $apiKey = bin2hex(random_bytes(ApiKeyService::API_KEY_LENGTH / 2));
        ApiKeyFactory::createOne([
            'project' => $project,
            'key_hashed' => hash('sha256', $apiKey),
            'scopes' => [RelayScope::SENDS_READ->value],
            'allowed_ips' => $allowedIps,
        ]);

        $this->client->request(
            'GET',
            '/api/console/sends',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $apiKey,
                'HTTP_X_FORWARDED_FOR' => $clientIp,
            ]
        );
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_api_key_with_allowed_ips_rejects_non_matching_ip(): void
    {
        $project = ProjectFactory::createOne();
        $apiKey = bin2hex(random_bytes(ApiKeyService::API_KEY_LENGTH / 2));
        ApiKeyFactory::createOne([
            'project' => $project,
            'key_hashed' => hash('sha256', $apiKey),
            'scopes' => [RelayScope::SENDS_READ->value],
            'allowed_ips' => ['203.0.113.5'],
        ]);

        $this->client->request(
            'GET',
            '/api/console/sends',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $apiKey,
                'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
            ]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Client IP is not allowed for this API key.', $this->getJson()['message']);
    }

    public function test_api_key_without_allowed_ips_skips_check(): void
    {
        $project = ProjectFactory::createOne();
        $apiKey = bin2hex(random_bytes(ApiKeyService::API_KEY_LENGTH / 2));
        ApiKeyFactory::createOne([
            'project' => $project,
            'key_hashed' => hash('sha256', $apiKey),
            'scopes' => [RelayScope::SENDS_READ->value],
            'allowed_ips' => [],
        ]);

        $this->client->request(
            'GET',
            '/api/console/sends',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $apiKey,
                'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
            ]
        );
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_authorizes_via_session(): void
    {
        AuthFake::enableForSymfony(
            $this->container,
            ['id' => 1],
            new AuthUserOrganization(
                id: 1,
                name: 'Fake Organization',
                role: 'member'
            )
        );

        $project = ProjectFactory::createOne();
        ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
            'scopes' => [RelayScope::SENDS_READ->value],
        ]);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/sends",
            server: [
                "HTTP_X_PROJECT_ID" => $project->getId(),
                "HTTP_X_ORGANIZATION_ID" => "1",
            ]
        );
        $this->assertResponseStatusCodeSame(200);

        $authResults = $this->client->getRequest()->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);
        $this->assertInstanceOf(ConsoleAuthResults::class, $authResults);
        $resource = $authResults->getResource();
        $this->assertInstanceOf(Project::class, $resource);
        $this->assertSame($project->getId(), $resource->getId());
        $this->assertSame(1, $authResults->getNullableUser()?->id);
    }

    public function test_org_level_endpoint_works_with_org(): void
    {
        AuthFake::enableForSymfony(
            $this->container,
            ['id' => 1],
            new AuthUserOrganization(
                id: 1,
                name: 'Fake Organization',
                role: 'member'
            )
        );

        SudoUserFactory::createOne(['user_id' => 1]);

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));

        $this->client->request(
            "POST",
            "/api/console/projects",
            [
                'name' => 'Valid Project Name',
                'send_type' => 'transactional',
            ],
            server: [
                'HTTP_X_ORGANIZATION_ID' => '1',
            ]
        );

        $this->assertResponseStatusCodeSame(200);
    }

    public function test_org_level_endpoint_works_without_org(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        SudoUserFactory::createOne(['user_id' => 1]);

        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request(
            "GET",
            "/api/console/init",
        );
        $json = $this->getJson();
        $this->assertArrayHasKey('project_users', $json);
        $this->assertArrayHasKey('config', $json);
    }

    public function test_authorizes_via_cloud_token(): void
    {
        $project = ProjectFactory::createOne(['organization_id' => 10]);

        $cloudJwt = CloudJwt::fromArray([
            'iss' => 'https://api.hyvor.com',
            'sub' => '10',
            'iat' => (string) time(),
            'nbf' => (string) time(),
            'exp' => (string) (time() + 3600),
            'scope' => 'relay:sends.read',
            'src' => 'cloud:123',
        ]);

        $cloudApiService = $this->createMock(CloudApiService::class);
        $cloudApiService->method('decodeJwtToken')->willReturn($cloudJwt);
        $this->container->set(CloudApiService::class, $cloudApiService);

        $this->client->request(
            'GET',
            '/api/console/sends',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer cloud_jwt_token_example',
                'HTTP_X_PROJECT_ID' => (string) $project->getId(),
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $authResults = $this->client->getRequest()->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);
        $this->assertInstanceOf(ConsoleAuthResults::class, $authResults);
        $this->assertSame(AccessType::CLOUD_TOKEN, $authResults->getAccessType());
        $this->assertSame(10, $authResults->getOrganizationId());
    }
}
