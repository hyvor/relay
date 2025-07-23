<?php

namespace App\Api\Local;

use Hyvor\Internal\Auth\Auth;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class AuthorizationListener
{

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $env,
    )
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only console API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/local')) return;
        if ($this->env === 'dev') return;

        $ip = $event->getRequest()->getClientIp();

        if ($ip !== '127.0.0.1') {
            throw new AccessDeniedHttpException('Only requests from localhost are allowed.');
        }
    }

}