<?php

namespace App\Api\Console\Idempotency;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Entity\Project;
use App\Service\Idempotency\IdempotencyService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController', priority: 200)] // priority less than AuthorizationListener
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
class IdempotencyListener
{

    public function __construct(
        private IdempotencyService $idempotencyService
    )
    {
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/console')) return;

        $idempotencyKey = $request->headers->get('x-idempotency-key');
        if ($idempotencyKey === null) {
            return;
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            return;
        }

        $project = $request->attributes->get(AuthorizationListener::RESOLVED_PROJECT_ATTRIBUTE_KEY);
        // AuthorizationListener should have set this attribute
        assert($project instanceof Project);

        $idempotencyRecord = $this->idempotencyService->getIdempotencyRecordByProjectAndKey($project, $idempotencyKey);

        if ($idempotencyRecord === null) {
            return;
        }

        // If the record exists, we can return the response immediately
        // $json =
    }

    public function onResponse(ResponseEvent $event): void
    {

    }

}