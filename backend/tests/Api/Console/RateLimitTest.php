<?php

namespace App\Tests\Api\Console;

use App\Api\Console\RateLimit\RateLimit;
use App\Api\Console\RateLimit\RateLimitListener;
use App\Service\App\RateLimit\RateLimiterProvider;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\CloudApi\CloudApiService;
use Hyvor\Internal\CloudApi\CloudJwt;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RateLimitListener::class)]
#[CoversClass(RateLimit::class)]
#[CoversClass(RateLimiterProvider::class)]
class RateLimitTest extends WebTestCase
{

    public function test_adds_rate_limit_headers(): void
    {
        $project = ProjectFactory::createOne();

        $this->consoleApi($project, "GET", "/sends");

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('X-RateLimit-Limit', '100');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '99');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '0');
    }

    public function test_adds_rate_limit_for_session_auth(): void
    {
        $authFake = $this->getService(AuthFake::class);
        $authFake->setUser(['id' => 1]);
        $authFake->setOrganization(id: 1);

        $project = ProjectFactory::createOne(['organization_id' => 1]);
        ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
        ]);

        $this->consoleApi($project, "GET", "/sends", useSession: true);

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('X-RateLimit-Limit', '60');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '59');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '0');
    }

    public function test_429_on_rate_limited(): void
    {
        $authFake = $this->getService(AuthFake::class);
        $authFake->setUser(['id' => 1]);
        $authFake->setOrganization(id: 1);

        $project = ProjectFactory::createOne();
        ProjectUserFactory::createOne([
            'project' => $project,
            'user_id' => 1,
        ]);

        $rateLimit = new RateLimit();
        /** @var RateLimiterProvider $rateLimiterProvider */
        $rateLimiterProvider = $this->getContainer()->get(RateLimiterProvider::class);

        $limiter = $rateLimiterProvider->rateLimiter($rateLimit->session(), "user:1");
        $limiter->consume(60);
        $limiter->consume(10);

        $response = $this->consoleApi($project, "GET", "/sends", useSession: true);

        $this->assertResponseStatusCodeSame(429);

        $this->assertResponseHeaderSame('X-RateLimit-Limit', '60');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '0');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '60');
    }

    public function test_for_sends_endpoint(): void
    {
        $project = ProjectFactory::createOne();
        ProjectUserFactory::createOne([
            'project' => $project,
        ]);

        $response = $this->consoleApi($project, "POST", "/sends");

        $this->assertResponseStatusCodeSame(422);

        $this->assertResponseHeaderSame('X-RateLimit-Limit', '10');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '9');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '0');
    }

    public function test_for_cloud_api_org_endpoints(): void
    {
        $cloudApiServiceMock = $this->createMock(CloudApiService::class);
        $cloudApiServiceMock
            ->method('decodeJwtToken')
            ->willReturn(
                CloudJwt::fromArray([
                    'iss' => 'https://api.hyvor.com',
                    'sub' => 'org:1',
                    'iat' => time(),
                    'nbf' => time(),
                    'exp' => time() + 3600,
                    'scope' => 'relay:org.projects.create',
                    'src' => 'dev:test',
                ]),
            );

        $this->getContainer()->set(CloudApiService::class, $cloudApiServiceMock);

        $project = ProjectFactory::createOne();
        $response = $this->consoleApi($project, "POST", "/projects", data: [
            'name' => 'Test Project',
            'send_type' => 'transactional',
        ], bearerToken: 'cloud-api-key');
        $this->assertResponseStatusCodeSame(200);

        $this->assertResponseHeaderSame('X-RateLimit-Limit', '60');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '59');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '0');
    }

}
