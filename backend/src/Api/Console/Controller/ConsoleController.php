<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\ProjectUser\ProjectUserService;
use App\Service\Send\Compliance;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ScopeRequired;
use Hyvor\Internal\InternalConfig;
use Hyvor\Internal\Sudo\SudoUserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Type\WebhooksEventEnum;
use Hyvor\Internal\Bundle\Api\DataCarryingHttpException;

class ConsoleController extends AbstractController
{

    public function __construct(
        private ProjectUserService $projectUserService,
        private InternalConfig $internalConfig,
        private LoggerInterface $logger,
        private InstanceService $instanceService,
        private SudoUserService $sudoUserService,
        private AuthInterface $auth,
    ) {}

    #[Route('/init', methods: 'GET')]
    public function initConsole(Request $request): JsonResponse
    {
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
        $instance = $this->instanceService->getInstance();

        $projectUsers = [];

        if ($org) {
            $projectUsers = $this->projectUserService->getProjectsOfUserInOrg($user->id, $org->id);
            $projectUsers = array_map(
                fn($project) => new ProjectUserObject($project, $user),
                $projectUsers
            );
        }

        $this->logger->info('Console initialized', [
            'organization_id' => $org?->id,
            'user_id' => $user->id,
            'user_name' => $user->name ?? $user->username,
            'project_count' => count($projectUsers),
        ]);

        return new JsonResponse([
            'project_users' => $projectUsers,
            'config' => [
                'deployment' => $this->internalConfig->getDeployment()->value,
                'hyvor' => [
                    'instance' => $this->internalConfig->getInstance(),
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->username,
                    'email' => $user->email,
                    'picture_url' => $user->picture_url,
                    'is_sudo' => $this->sudoUserService->exists($user->id),
                ],
                'app' => [
                    'system_project_id' => $instance->getSystemProject()->getId(),
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
            'organization' => $org
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
