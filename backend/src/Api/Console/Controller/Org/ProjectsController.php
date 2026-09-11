<?php

namespace App\Api\Console\Controller\Org;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\OrganizationLevelEndpoint;
use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Object\ProjectUserObject;
use App\Service\Project\ProjectService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ProjectsController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService
    ) {
    }

    #[Route('/project', methods: 'POST')]
    #[OrganizationLevelEndpoint]
    #[OrgEndpoint]
    #[OA\Post(
        summary: 'Create a project',
        description: 'Creates a new project in the current organization and adds the authenticated user to it.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the project-user object of the creator in the new project.',
        content: new Model(type: ProjectUserObject::class)
    )]
    public function create(#[MapRequestPayload] CreateProjectInput $input, Request $request): JsonResponse
    {
        $user = AuthorizationListener::getUser($request);
        $org = AuthorizationListener::getOrganization($request);

        $newProject = $this->projectService->createProject(
            $user->id,
            $org->id,
            $input->name,
            $input->send_type
        );

        return $this->json(new ProjectUserObject($newProject['projectUser'], $user));
    }
}
