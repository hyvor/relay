<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\UserLevelEndpoint;
use App\Api\Console\Object\ProjectObject;
use App\Entity\Project;
use App\Service\Project\ProjectService;
use App\Service\Send\Compliance;
use Hyvor\Internal\Bundle\Security\HasHyvorUser;
use Hyvor\Internal\InternalConfig;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Type\WebhooksEventEnum;
use App\Api\Console\Authorization\Scope;

class ConsoleController extends AbstractController
{
    use HasHyvorUser;

    public function __construct(
        private ProjectService $projectService,
        private InternalConfig $internalConfig,
        private LoggerInterface $logger
    )
    {
    }

    #[Route('/init', methods: 'GET')]
    #[UserLevelEndpoint]
    public function initConsole(): JsonResponse
    {
        $this->logger->info("TESTING");
        $user = $this->getHyvorUser();

        $projectUsers = $this->projectService->getUsersProject($user->id);
        $projectUsers = array_map(
            fn($project) => new ProjectObject($project),
            $projectUsers->toArray()
        );

        return new JsonResponse([
            'projects' => $projectUsers,
            'config' => [
                'hyvor' => [
                    'instance' => $this->internalConfig->getInstance(),
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
    public function initProject(Project $project): JsonResponse
    {
        return new JsonResponse([
            'project' => new ProjectObject($project),
        ]);
    }
}
