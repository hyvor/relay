<?php

namespace App\Tests\Api\Console;

use App\Api\Console\RateLimit\RateLimit;
use App\Api\Console\RateLimit\RateLimitListener;
use App\Service\App\RateLimit\RateLimiterProvider;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RateLimitListener::class)]
#[CoversClass(RateLimit::class)]
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
        $project = ProjectFactory::createOne(['hyvor_user_id' => 1]);

        $this->consoleApi($project, "GET", "/sends", useSession: true);

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('X-RateLimit-Limit', '60');
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '59');
        $this->assertResponseHeaderSame('X-RateLimit-Reset', '0');
    }

    public function test_429_on_rate_limited(): void
    {
        $project = ProjectFactory::createOne(['hyvor_user_id' => 1]);

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

}