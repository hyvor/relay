<?php

namespace App\Tests\Api\Sudo;

use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SudoUserFactory;
use Symfony\Component\BrowserKit\Cookie;

class SudoAuthorizationTest extends WebTestCase
{
    public function test_sudo_api_access_with_valid_sudo_user(): void
    {
        $sudoUser = SudoUserFactory::createOne(
            [
                'hyvorUserId' => 1,
            ]
        );
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request("GET", "/api/sudo/servers");
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_sudo_api_access_with_invalid_sudo_user(): void
    {
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request("GET", "/api/sudo/servers");
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("You do not have sudo access.", $this->getJson()["message"]);
    }

    public function test_sudo_api_access_without_session(): void
    {
        $this->client->request("GET", "/api/sudo/servers");
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Session authentication required for sudo API access.", $this->getJson()["message"]);
    }
} 
