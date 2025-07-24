<?php

namespace App\Tests\Api\Local;

use App\Api\Local\LocalAuthorizationListener;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

    public function test_ignores_non_local_apis(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = new LocalAuthorizationListener('prod');
        $request = Request::create('/api/remote/some-endpoint');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

    public function test_ignores_dev(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = new LocalAuthorizationListener('dev');
        $request = Request::create('/api/local/some-endpoint');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

    public function test_throws_for_non_local_ip(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only requests from localhost are allowed.');

        $listener = new LocalAuthorizationListener('prod');
        $request = Request::create('/api/local/some-endpoint');
        $request->server->set('REMOTE_ADDR', '9.9.9.9');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener($event);
    }

}