<?php

namespace App\Api;

use Hyvor\Internal\Bundle\Api\AbstractApiExceptionListener;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener extends AbstractApiExceptionListener
{

    /**
     * @codeCoverageIgnore
     */
    protected function prefix(): string
    {
        return '/api';
    }
}