<?php

namespace App\Api\Console\Authorization;

use App\Service\ApiKey\ApiKeyService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER)]
class AuthorizationListener
{

    public const string RESOLVED_PROJECT_ATTRIBUTE_KEY = 'console_api_resolved_project';

    public function __construct(
        private ApiKeyService $apiKeyService
    )
    {
    }

    public function __invoke(ControllerEvent $event): void
    {

        // only console API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/console')) {
            return;
        }

        $request = $event->getRequest();

        if ($request->headers->has('authorization')) {
            $this->handleAuthorizationHeader($event);
        }

        // TODO: handle session-based authentication

    }

    private function handleAuthorizationHeader(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $authorizationHeader = $request->headers->get('authorization');
        assert(is_string($authorizationHeader));

        if (!str_starts_with($authorizationHeader, 'Bearer ')) {
            throw new AccessDeniedHttpException('Authorization header must start with "Bearer ".');
        }

        $apiKey = trim(substr($authorizationHeader, 7));

        if ($apiKey === '') {
            throw new AccessDeniedHttpException('API key is missing or empty.');
        }

        $apiKeyModel = $this->apiKeyService->getByRawKey($apiKey);

        if ($apiKeyModel === null) {
            throw new AccessDeniedHttpException('Invalid API key.');
        }

        $scopes = $apiKeyModel->getScopes();
        $this->verifyScopes($scopes, $event);

        $project = $apiKeyModel->getProject();
        $request->attributes->set(self::RESOLVED_PROJECT_ATTRIBUTE_KEY, $project);
    }

    /**
     * @param string[] $scopes
     */
    private function verifyScopes(array $scopes, ControllerEvent $event): void
    {

        $attributes = $event->getAttributes();
        $scopeRequiredAttribute = $attributes[ScopeRequired::class][0] ?? null;

        if (!$scopeRequiredAttribute instanceof ScopeRequired) {
            throw new \Exception('Every controller method that requires authorization must have a ScopeRequired attribute.');
        }

        $requiredScope = $scopeRequiredAttribute->scope->value;

        if (!in_array($requiredScope, $scopes, true)) {
            throw new AccessDeniedHttpException(
                "You do not have the required scope '$requiredScope' to access this resource."
            );
        }

    }

}