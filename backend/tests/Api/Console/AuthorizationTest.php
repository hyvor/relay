<?php

namespace App\Tests\Api\Console;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthorizationListener::class)]
class AuthorizationTest extends WebTestCase
{
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
}
