<?php

namespace App\Api\Console\Authorization;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use App\Service\Project\ProjectService;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Auth\AuthUserOrganization;
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
    public const string RESOLVED_ORGANIZATION_ATTRIBUTE_KEY = 'console_api_resolved_organization';

    public function __construct(
        private ProjectService $projectService,
        private ProjectUserService $projectUserService,
        private ApiKeyService $apiKeyService,
		private AuthInterface $auth,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        // only console API requests
        // @codeCoverageIgnoreStart
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/console')) {
            return;
        }
        if ($event->isMainRequest() === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

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
        $isOrgLevelEndpoint = count($event->getAttributes(OrganizationLevelEndpoint::class)) > 0;
        $noOrganizationRequired = count($event->getAttributes(OrganizationOptional::class)) > 0;

		$me = $this->auth->me($request);
        if ($me === null) {
            throw new DataCarryingHttpException(
                401,
                [
                    'login_url' => $this->auth->authUrl('login'),
                    'signup_url' => $this->auth->authUrl('signup'),
                ],
                'Unauthorized'
            );
		}

		$user = $me->getUser();
		$org = $me->getOrganization();

		$request->attributes->set(self::RESOLVED_USER_ATTRIBUTE_KEY, $user);
        $request->attributes->set(self::RESOLVED_ORGANIZATION_ATTRIBUTE_KEY, $org);

		if ($noOrganizationRequired) {
            assert($isOrgLevelEndpoint === true);
			return;
		}

		if ($org === null) {
			throw new AccessDeniedHttpException('Organization is required');
		}

		$orgFromReq = (int)$request->headers->get('X-Organization-ID');

		if ($orgFromReq !== $org->id) {
			throw new AccessDeniedHttpException('org_mismatch');
		}


        // user-level endpoints do not have a project ID
        if ($isOrgLevelEndpoint === false) {
            if ($projectId === null) {
                throw new AccessDeniedHttpException('X-Project-ID is required for this endpoint.');
            }

            $project = $this->projectService->getProjectById((int)$projectId);

            if ($project === null) {
                throw new AccessDeniedHttpException('Invalid project ID.');
			}

			if ($project->getOrganizationId() !== $org->id) {
				throw new AccessDeniedHttpException('This project does not belong to your current organization.');
			}

            $projectUser = $this->projectUserService->getProjectUser($project, $user->id);

            if ($projectUser === null) {
                throw new AccessDeniedHttpException('You do not have access to this project.');
			}

            $this->verifyScopes($projectUser->getScopes(), $event);

            $request->attributes->set(self::RESOLVED_PROJECT_ATTRIBUTE_KEY, $project);
        }
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

    public static function hasOrganization(Request $request): bool
    {
        return $request->attributes->has(self::RESOLVED_ORGANIZATION_ATTRIBUTE_KEY)
            && $request->attributes->get(self::RESOLVED_ORGANIZATION_ATTRIBUTE_KEY) instanceof AuthUserOrganization;
	}

    // only call after hasOrganization()
    public static function getOrganization(Request $request): AuthUserOrganization
    {
        $user = $request->attributes->get(self::RESOLVED_ORGANIZATION_ATTRIBUTE_KEY);
        assert($user instanceof AuthUserOrganization, 'Organization must be an instance of AuthUserOrganization');
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
