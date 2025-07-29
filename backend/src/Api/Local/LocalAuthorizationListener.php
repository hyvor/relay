<?php

namespace App\Api\Local;

use App\Service\Instance\InstanceService;
use App\Service\Ip\ServerIp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class LocalAuthorizationListener
{

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $env,

        private InstanceService $instanceService,
    )
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only console API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/local')) return;
        if ($this->env === 'dev') return;

        $ip = $event->getRequest()->getClientIp();
        $allowPrivateNetwork = count($event->getAttributes(AllowPrivateNetwork::class)) > 0;

        if ($this->isIpAllowed($ip, $allowPrivateNetwork) === false) {
            $privateNetworkMsg = $allowPrivateNetwork ?
                " or private network" :
                "";
            throw new AccessDeniedHttpException(
                "Only requests from localhost$privateNetworkMsg are allowed. Current IP is: $ip"
            );
        }
    }

    private function isIpAllowed(?string $ip, bool $allowPrivateNetwork): bool
    {

        if ($ip === null) return false;
        if ($ip === '127.0.0.1') return true;
        if ($ip === '::1') return true; // IPv6 localhost

        if ($allowPrivateNetwork) {
            $privateNetworkCidr = $this->instanceService->tryGetInstance()?->getPrivateNetworkCidr() ?? ServerIp::DEFAULT_PRIVATE_IP_RANGE;
            if (IpUtils::checkIp4($ip, $privateNetworkCidr)) {
                return true;
            }
        }

        return false;

    }

}