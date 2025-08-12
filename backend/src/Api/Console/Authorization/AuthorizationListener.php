<?php

namespace App\Api\Console\Authorization;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use App\Service\Project\ProjectService;
use Hyvor\Internal\Bundle\Api\DataCarryingHttpException;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Auth\AuthUser;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 200)]
class AuthorizationListener
{

    use ClockAwareTrait;

    public const string RESOLVED_PROJECT_ATTRIBUTE_KEY = 'console_api_resolved_project';
    public const string RESOLVED_API_KEY_ATTRIBUTE_KEY = 'console_api_resolved_api_key';
    public const string RESOLVED_USER_ATTRIBUTE_KEY = 'console_api_resolved_user';

    public function __construct(
        private ProjectService $projectService,
        private ApiKeyService $apiKeyService,
        private AuthInterface $auth,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only console API requests
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/console')) {
            return;
        }
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();

        if ($request->headers->has('authorization')) {
            $this->handleAuthorizationHeader($event);
        } else {
            $this->handleSession($event);
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

        $request->attributes->set(self::RESOLVED_API_KEY_ATTRIBUTE_KEY, $apiKeyModel);
        $request->attributes->set(self::RESOLVED_PROJECT_ATTRIBUTE_KEY, $project);

        $apiKeyUpdates = new UpdateApiKeyDto();
        $apiKeyUpdates->lastAccessedAt = $this->now();
        $this->apiKeyService->updateApiKey($apiKeyModel, $apiKeyUpdates);
    }

    private function handleSession(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $projectId = $request->headers->get('x-project-id');
        $isUserLevelEndpoint = count($event->getAttributes(UserLevelEndpoint::class)) > 0;

        $user = $this->auth->check($request);

        if ($user === false) {
            throw new DataCarryingHttpException(
                401,
                [
                    'login_url' => $this->auth->authUrl('login'),
                    'signup_url' => $this->auth->authUrl('signup'),
                ],
                'Unauthorized'
            );
        }

        // user-level endpoints do not have a project ID
        if ($isUserLevelEndpoint === false) {
            if ($projectId === null) {
                throw new AccessDeniedHttpException('X-Project-ID is required for this endpoint.');
            }

            $project = $this->projectService->getProjectById((int)$projectId);

            if ($project === null) {
                throw new AccessDeniedHttpException('Invalid project ID.');
            }

            if ($project->getUserId() !== $user->id) {
                // to-do: later user scopes are needed
                throw new AccessDeniedHttpException('You do not have access to this project.');
            }

            $request->attributes->set(self::RESOLVED_PROJECT_ATTRIBUTE_KEY, $project);
        }

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

    public static function hasUser(Request $request): bool
    {
        return $request->attributes->has(self::RESOLVED_USER_ATTRIBUTE_KEY);
    }

    // only call after hasUser()
    public static function getUser(Request $request): AuthUser
    {
        $user = $request->attributes->get(self::RESOLVED_USER_ATTRIBUTE_KEY);
        assert($user instanceof AuthUser, 'User must be an instance of AuthUser');
        return $user;
    }

    // make sure project is set before calling this
    public static function getProject(Request $request): Project
    {
        $project = $request->attributes->get(self::RESOLVED_PROJECT_ATTRIBUTE_KEY);
        assert($project instanceof Project);
        return $project;
    }

    // make sure API key is set before calling this
    public static function getApiKey(Request $request): ApiKey
    {
        $apiKey = $request->attributes->get(self::RESOLVED_API_KEY_ATTRIBUTE_KEY);
        assert($apiKey instanceof ApiKey);
        return $apiKey;
    }

}
