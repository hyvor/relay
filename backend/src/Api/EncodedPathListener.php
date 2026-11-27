<?php

namespace App\Api;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Authorization listeners match on the raw (non-decoded) path, while the router decodes it.
 * To prevent bypassing them (e.g. /api/%73udo/...), percent-encoded API paths are rejected.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
class EncodedPathListener
{

    public function __invoke(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();

        if (str_starts_with(rawurldecode($path), '/api') && str_contains($path, '%')) {
            throw new NotFoundHttpException('Not found');
        }
    }

}
