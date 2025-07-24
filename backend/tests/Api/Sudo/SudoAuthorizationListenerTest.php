<?php

namespace App\Tests\Api\Sudo;

use App\Api\Sudo\Authorization\SudoAuthorizationListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\SudoUserFactory;
use Hyvor\Internal\Auth\AuthFake;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(SudoAuthorizationListener::class)]
class SudoAuthorizationListenerTest extends WebTestCase
{

    protected function shouldEnableAuthFake(): bool
    {
        return false;
    }

    private function getListener(): SudoAuthorizationListener
    {
        $listener = $this->getContainer()->get(SudoAuthorizationListener::class);
        assert($listener instanceof SudoAuthorizationListener);
        return $listener;
    }

    private function getKernel(): KernelInterface
    {
        $kernel = self::$kernel;
        assert($kernel instanceof KernelInterface);
        return $kernel;
    }

    public function test_ignores_non_sudo_api_requests(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = $this->getListener();
        $request = Request::create('/api/console/some-endpoint');

        $event =  new ControllerEvent(
            $this->getKernel(),
            fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

    public function test_sudo_api_access_with_valid_sudo_user(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);
        $sudoUser = SudoUserFactory::createOne(
            [
                'hyvorUserId' => 1,
            ]
        );
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request("GET", "/api/sudo/servers");
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_sudo_api_access_with_guest_user(): void
    {
        AuthFake::enableForSymfony($this->container, null);
        $this->client->getCookieJar()->set(new Cookie('authsess', 'validSession'));
        $this->client->request("GET", "/api/sudo/servers");
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame("Invalid session.", $this->getJson()["message"]);
    }

    public function test_sudo_api_access_with_invalid_sudo_user(): void
    {
        AuthFake::enableForSymfony($this->container, ['id' => 1]);
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
