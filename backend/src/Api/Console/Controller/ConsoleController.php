<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\OrganizationLevelEndpoint;
use App\Api\Console\Authorization\OrganizationOptional;
use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Entity\Type\WebhooksEventEnum;
use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\ProjectUser\ProjectUserService;
use App\Service\Send\Compliance;
use Hyvor\Internal\InternalConfig;
use Hyvor\Internal\Sudo\SudoUserService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ConsoleController extends AbstractController
{
    public function __construct(
        private ProjectUserService $projectUserService,
        private InternalConfig $internalConfig,
        private LoggerInterface $logger,
        private InstanceService $instanceService,
        private SudoUserService $sudoUserService,
    ) {}

    #[Route('/init', methods: 'GET')]
    #[OrganizationLevelEndpoint]
    #[OrganizationOptional]
    #[OA\Get(
        summary: 'Initialize the console',
        description: 'Returns the authenticated user, their projects in the organization, and global app configuration.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Console initialization payload',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'project_users',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: ProjectUserObject::class))
                ),
                new OA\Property(
                    property: 'config',
                    properties: [
                        new OA\Property(property: 'deployment', type: 'string'),
                        new OA\Property(
                            property: 'hyvor',
                            properties: [
                                new OA\Property(property: 'instance', type: 'string'),
                            ]
                        ),
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                                new OA\Property(property: 'picture_url', type: 'string', nullable: true),
                                new OA\Property(property: 'is_sudo', type: 'boolean'),
                            ]
                        ),
                        new OA\Property(
                            property: 'app',
                            properties: [
                                new OA\Property(property: 'system_project_id', type: 'integer'),
                                new OA\Property(
                                    property: 'webhook',
                                    properties: [
                                        new OA\Property(
                                            property: 'events',
                                            type: 'array',
                                            items: new OA\Items(type: 'string')
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'api_keys',
                                    properties: [
                                        new OA\Property(
                                            property: 'scopes',
                                            type: 'array',
                                            items: new OA\Items(type: 'string')
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'compliance',
                                    properties: [
                                        new OA\Property(property: 'bounce_rate_warning', type: 'number', format: 'float'),
                                        new OA\Property(property: 'bounce_rate_error', type: 'number', format: 'float'),
                                        new OA\Property(property: 'complaint_rate_warning', type: 'number', format: 'float'),
                                        new OA\Property(property: 'complaint_rate_error', type: 'number', format: 'float'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                ),
                new OA\Property(property: 'organization', type: 'object', nullable: true),
            ]
        )
    )]
    public function init(Request $request): JsonResponse
    {
        $user = AuthorizationListener::getUser($request);
        $org = AuthorizationListener::hasOrganization($request)
            ? AuthorizationListener::getOrganization($request)
            : null;
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
    #[OA\Get(
        summary: 'Initialize the project',
        description: 'Returns the current project object.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the project object wrapped in a config object.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'project', ref: new Model(type: ProjectObject::class)),
            ]
        )
    )]
    public function initProject(Project $project): JsonResponse
    {
        return new JsonResponse([
            'project' => new ProjectObject($project),
        ]);
    }
}
