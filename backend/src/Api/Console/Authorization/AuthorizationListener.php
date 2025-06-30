<?php

namespace App\Api\Console\Authorization;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST)]
class AuthorizationListener
{

    public function __invoke(RequestEvent $event): void
    {
        dd($event);
    }

}