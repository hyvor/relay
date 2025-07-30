<?php

namespace App\Tests\Api\Local;

use App\Api\Local\AllowPrivateNetwork;
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
        /** @var InstanceService $instanceService */
        $instanceService = $this->container->get(InstanceService::class);

        return new LocalAuthorizationListener($env, $instanceService);
    }

    public function test_ignores_non_local_apis(): void
    {
        $this->expectNotToPerformAssertions();

        $listener = $this->getListener('prod');
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

        $listener = $this->getListener('dev');
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

        $listener = $this->getListener('prod');
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

    public function test_allows_private_network_with_attribute(): void
    {

        $this->expectNotToPerformAssertions();
//        $this->expectException(AccessDeniedHttpException::class);
//        $this->expectExceptionMessage('Only requests from localhost or private network are allowed.');

        $listener = $this->getListener('prod');
        $request = Request::create('/api/local/some-endpoint');
        $request->server->set('REMOTE_ADDR', '10.0.0.0');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $event->setController(
            fn () => null,
            [
                AllowPrivateNetwork::class => [new AllowPrivateNetwork()]
            ]
        );

        $listener($event);

    }

    public function test_does_not_allow_outside_of_private_network(): void
    {

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Only requests from localhost or private network are allowed.');

        InstanceFactory::createOne([
            'private_network_cidr' => '172.16.0.0/12'
        ]);

        $listener = $this->getListener('prod');
        $request = Request::create('/api/local/some-endpoint');
        $request->server->set('REMOTE_ADDR', '10.0.0.0');
        $event = new ControllerEvent(
            $this->getKernel(),
            function () {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $event->setController(
            fn () => null,
            [
                AllowPrivateNetwork::class => [new AllowPrivateNetwork()]
            ]
        );

        $listener($event);

    }

}