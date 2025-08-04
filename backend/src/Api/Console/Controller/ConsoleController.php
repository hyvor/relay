<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\UserLevelEndpoint;
use App\Api\Console\Object\ProjectObject;
use App\Entity\Project;
use App\Service\App\Config;
use App\Service\Project\ProjectService;
use App\Service\Send\Compliance;
use Hyvor\Internal\Auth\AuthUser;
use Hyvor\Internal\InternalConfig;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Type\WebhooksEventEnum;
use App\Api\Console\Authorization\Scope;

class ConsoleController extends AbstractController
{

    public function __construct(
        private ProjectService $projectService,
        private InternalConfig $internalConfig,
        private Config $appConfig,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/init', methods: 'GET')]
    #[UserLevelEndpoint]
    public function initConsole(Request $request): JsonResponse
    {
        $user = AuthorizationListener::getUser($request);

        $projectUsers = $this->projectService->getUsersProject($user->id);
        $projectUsers = array_map(
            fn($project) => new ProjectObject($project),
            $projectUsers->toArray()
        );

        $this->logger->info('Console initialized', [
            'user_id' => $user->id,
            'user_name' => $user->name ?? $user->username,
            'project_count' => count($projectUsers),
        ]);

        return new JsonResponse([
            'projects' => $projectUsers,
            'config' => [
                'hosting' => $this->appConfig->getHosting(),
                'hyvor' => [
                    'instance' => $this->internalConfig->getInstance(),
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->username,
                    'email' => $user->email,
                    'picture_url' => $user->picture_url,
                ],
                'app' => [
                    'webhook' => [
                        'events' => array_map(fn($event) => $event->value, WebhooksEventEnum::cases()),
                    ],
                    'api_keys' => [
                        'scopes' => array_map(fn($scope) => $scope->value, Scope::cases()),
                    ],
                    'compliance' => [
                        'rates' => Compliance::getRates(),
                    ],
                ],
            ],
        ]);
    }

    #[Route('/init/project', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_READ)]
    public function initProject(Project $project): JsonResponse
    {
        return new JsonResponse([
            'project' => new ProjectObject($project),
        ]);
    }
}
