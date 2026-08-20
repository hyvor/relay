<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\OrganizationLevelEndpoint;
use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Input\UpdateProjectInput;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\ProjectService;
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

    #[Route('/project', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_READ)]
    #[OA\Get(
        summary: 'Get the current project',
        description: 'Returns the project that the current API key or session belongs to.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the project object.',
        content: new Model(type: ProjectObject::class)
    )]
    public function get(Project $project): JsonResponse
    {
        return $this->json(new ProjectObject($project));
    }

    #[Route('/project', methods: 'PATCH')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    #[OA\Patch(
        summary: 'Update the current project',
        description: 'Updates the current project. Only the project name can be changed.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the updated project object.',
        content: new Model(type: ProjectObject::class)
    )]
    public function update(#[MapRequestPayload] UpdateProjectInput $input, Project $project): JsonResponse
    {
        $updates = new UpdateProjectDto();

        if ($input->hasProperty('name')) {
            $updates->name = $input->name;
        }

        $updatedProject = $this->projectService->updateProject($project, $updates);

        return $this->json(new ProjectObject($updatedProject));
    }
}
