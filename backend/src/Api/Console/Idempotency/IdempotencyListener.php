<?php

namespace App\Api\Console\Idempotency;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Entity\Project;
use App\Service\Idempotency\IdempotencyService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController', priority: 100)] // priority less than AuthorizationListener
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
class IdempotencyListener
{

    const IDEMPOTENCY_KEY_IN_REQUEST = 'idempotency_key_in_request';

    public function __construct(
        private IdempotencyService $idempotencyService
    )
    {
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $endpoint = $request->getPathInfo();
        if (!str_starts_with($endpoint, '/api/console')) return;
        if ($event->isMainRequest() === false) return;

        $idempotencyKey = $request->headers->get('x-idempotency-key');
        if ($idempotencyKey === null) return;
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') return;

        $endpointHasIdempotencySupport = count($event->getControllerReflector()->getAttributes(IdempotencySupported::class)) > 0;
        if (!$endpointHasIdempotencySupport) {
            throw new BadRequestException('This endpoint does not support idempotency. Retry without the "X-Idempotency-Key" header.');
        }

        $project = $request->attributes->get(AuthorizationListener::RESOLVED_PROJECT_ATTRIBUTE_KEY);
        // AuthorizationListener should have set this attribute
        assert($project instanceof Project);

        $idempotencyRecord = $this->idempotencyService->getIdempotencyRecordByProjectEndpointAndKey(
            $project,
            $endpoint,
            $idempotencyKey
        );

        if ($idempotencyRecord === null) {
            $request->attributes->set(self::IDEMPOTENCY_KEY_IN_REQUEST, $idempotencyKey);
            return;
        }

        // If the record exists, we can return the response immediately
        $json = $idempotencyRecord->getResponse();
        $status = $idempotencyRecord->getStatusCode();

        $response = new JsonResponse($json, $status);
        $response->headers->set('X-Idempotency-Short-Circuit', 'true');

        $event->setController(fn () => $response);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $idempotencyKey = $request->attributes->get(self::IDEMPOTENCY_KEY_IN_REQUEST);
        if (!is_string($idempotencyKey)) return;

        // If the request has idempotency support, we need to save the response
        $response = $event->getResponse();

        // if it is not JSON, it is probably a 500 error or something else that does not need idempotency
        if (!$response instanceof JsonResponse) {
            return;
        }

        // Do not save 500 error responses
        if ($response->getStatusCode() >= 500) {
            return;
        }

        // If the response is rate limited (429), we do not save it
        if ($response->getStatusCode() === 429) {
            return;
        }

        $endpoint = $request->getPathInfo();
        $project = $request->attributes->get(AuthorizationListener::RESOLVED_PROJECT_ATTRIBUTE_KEY);
        assert($project instanceof Project);

        $this->idempotencyService->createIdempotencyRecord(
            $project,
            $endpoint,
            $idempotencyKey,
            $response,
        );

    }

}