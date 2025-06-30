<?php

namespace App\Api\Console\Authorization;

use App\Service\ApiKey\ApiKeyService;
use App\Service\Project\ProjectService;
use Hyvor\Internal\Auth\Auth;
use Hyvor\Internal\Auth\AuthInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 200)]
class AuthorizationListener
{

    public const string RESOLVED_PROJECT_ATTRIBUTE_KEY = 'console_api_resolved_project';
    public const string RESOLVED_USER_ATTRIBUTE_KEY = 'console_api_resolved_user';

    public function __construct(
        private ProjectService $projectService,
        private ApiKeyService $apiKeyService,
        private AuthInterface $auth,
    )
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only console API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/console')) return;
        if ($event->isMainRequest() === false) return;

        $request = $event->getRequest();

        if ($request->headers->has('authorization')) {
            $this->handleAuthorizationHeader($event);
        } else if ($request->headers->has('x-project-id') && $request->cookies->has(Auth::HYVOR_SESSION_COOKIE_NAME)) {
            $this->handleSession($event);
        } else {
            throw new AccessDeniedHttpException('Authorization method not supported. Use either Bearer token or a session.');
        }
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

    private function handleSession(ControllerEvent $event): void
    {

        $request = $event->getRequest();
        $projectId = $request->headers->get('x-project-id');
        $sessionCookie = $request->cookies->get(Auth::HYVOR_SESSION_COOKIE_NAME);

        assert($projectId !== null);
        assert($sessionCookie !== null);

        $project = $this->projectService->getProjectById((int) $projectId);

        if ($project === null) {
            throw new AccessDeniedHttpException('Invalid project ID.');
        }

        $user = $this->auth->check((string) $sessionCookie);

        if ($user === false) {
            throw new AccessDeniedHttpException('Invalid session.');
        }

        if ($project->getHyvorUserId() !== $user->id) {
            // to-do: later user scopes are needed
            throw new AccessDeniedHttpException('You do not have access to this project.');
        }

        $request->attributes->set(self::RESOLVED_PROJECT_ATTRIBUTE_KEY, $project);
        $request->attributes->set(self::RESOLVED_USER_ATTRIBUTE_KEY, $user);

    }

    /**
     * @param string[] $scopes
     */
    private function verifyScopes(array $scopes, ControllerEvent $event): void
    {

        $attributes = $event->getAttributes(ScopeRequired::class);
        $scopeRequiredAttribute = $attributes[0] ?? null;

        assert(
            $scopeRequiredAttribute instanceof ScopeRequired,
            'ScopeRequired attribute must be set on the controller method'
        );

        $requiredScope = $scopeRequiredAttribute->scope->value;

        if (!in_array($requiredScope, $scopes, true)) {
            throw new AccessDeniedHttpException(
                "You do not have the required scope '$requiredScope' to access this resource."
            );
        }

    }

}