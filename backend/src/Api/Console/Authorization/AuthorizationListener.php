<?php

namespace App\Api\Console\Authorization;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\AllowedIp;
use App\Service\ApiKey\ApiKeyService;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use App\Service\Project\ProjectService;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\CloudApi\CloudApiService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleApiAuthorizationListenerAbstract;
use Hyvor\Internal\InternalConfig;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @extends ConsoleApiAuthorizationListenerAbstract<Project>
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 200)]
class AuthorizationListener extends ConsoleApiAuthorizationListenerAbstract
{

    use ClockAwareTrait;

    public function __construct(
        private ProjectService $projectService,
        private ProjectUserService $projectUserService,
        private ApiKeyService $apiKeyService,
        private RequestStack $requestStack,
        InternalConfig $internalConfig,
        CloudApiService $cloudApiService,
        AuthInterface $auth,
    ) {
        parent::__construct(
            $internalConfig,
            $cloudApiService,
            $auth,
        );
    }

    protected function getBasePath(): string
    {
        return '/api/console';
    }

    protected function getBypassPaths(): array
    {
        return [
            '/api/console/init',
        ];
    }

    protected function isResourceApiKey(string $bearerToken): bool
    {
        return strlen($bearerToken) === ApiKeyService::API_KEY_LENGTH && ctype_xdigit($bearerToken);
    }

    /**
     * @return null|array{resource: Project, scopes: string[], apiKey: ApiKey}
     */
    protected function getResourceFromApiKey(string $apiKey): null|array
    {
        $apiKeyModel = $this->apiKeyService->getByRawKey($apiKey);

        if ($apiKeyModel === null) {
            return null;
        }

        $allowedIps = $apiKeyModel->getAllowedIps();
        /**
         * note: here we do not check if allowed IPs are set if sends.send is set
         * it is only validated at the time of creating an API key
         */
        if (count($allowedIps) > 0) {
            $request = $this->requestStack->getCurrentRequest();
            $clientIp = $request?->getClientIp();
            if ($clientIp === null || !AllowedIp::matches($clientIp, $allowedIps)) {
                throw new AccessDeniedHttpException('Client IP is not allowed for this API key.');
            }
        }

        return [
            'resource' => $apiKeyModel->getProject(),
            'scopes' => $apiKeyModel->getScopes(),
            'apiKey' => $apiKeyModel,
        ];
    }

    protected function getResourceFromRequest(ControllerEvent $event): ?object
    {
        $projectId = $event->getRequest()->headers->get('x-project-id');

        if ($projectId === null) {
            return null;
        }

        return $this->projectService->getProjectById((int) $projectId);
    }

    protected function getResourceFromRequestError(): string
    {
        return 'Unable to find the project from the request. Please provide a valid X-Project-ID header.';
    }

    /**
     * @param Project $resource
     */
    protected function getOrganizationIdFromResource(object $resource): int
    {
        $orgId = $resource->getOrganizationId();
        assert($orgId !== null);

        return $orgId;
    }

    /**
     * @param Project $resource
     * @return null|string[]
     */
    protected function getUserResourceScopes(object $resource, int $userId): null|array
    {
        $projectUser = $this->projectUserService->getProjectUser($resource, $userId);

        if ($projectUser === null) {
            return null;
        }

        return $projectUser->getScopes();
    }

    protected function onProductApiKeyUse(object $apiKeyModel): void
    {
        assert($apiKeyModel instanceof ApiKey);
        $apiKeyUpdates = new UpdateApiKeyDto();
        $apiKeyUpdates->lastAccessedAt = $this->now();
        $this->apiKeyService->updateApiKey($apiKeyModel, $apiKeyUpdates);
    }

}
