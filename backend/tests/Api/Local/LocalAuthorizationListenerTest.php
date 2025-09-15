<?php

namespace App\Tests\Api\Local;

use App\Api\Local\LocalAuthorizationListener;
use App\Service\Instance\InstanceService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(LocalAuthorizationListener::class)]
class LocalAuthorizationListenerTest extends KernelTestCase
{

    private function getKernel(): KernelInterface
    {
        $kernel = self::$kernel;
        assert($kernel instanceof KernelInterface);
        return $kernel;
    }

    private function getListener(string $env): LocalAuthorizationListener
    {
        return new LocalAuthorizationListener($env);
    }

    public function test_ignores_non_local_apis(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = $this->getListener('prod');
        $request = Request::create('/api/remote/some-endpoint');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

    public function test_ignores_dev(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = $this->getListener('dev');
        $request = Request::create('/api/local/some-endpoint');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

    public function test_throws_for_non_local_ip(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only requests from localhost are allowed.');

        $listener = $this->getListener('prod');
        $request = Request::create('/api/local/some-endpoint');
        $request->server->set('REMOTE_ADDR', '9.9.9.9');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }
}
