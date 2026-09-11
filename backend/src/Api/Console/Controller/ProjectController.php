<?php

namespace App\Api\Console\Controller;

use Hyvor\Internal\CloudApi\Scope\RelayScope;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\ProjectService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ScopeRequired;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Input\UpdateProjectInput;

class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService
    ) {
    }

    #[Route('/projects', methods: 'POST')]
    #[OrgEndpoint]
    #[ScopeRequired(RelayScope::ORG_PROJECTS_CREATE)]
    public function createProject(
        #[MapRequestPayload] CreateProjectInput $input,
        ConsoleAuthResults $consoleAuth,
    ): JsonResponse {
        $user = $consoleAuth->getNullableUser();
        $organizationId = $consoleAuth->getOrganizationId();

        $newProject = $this->projectService->createProject(
            $user !== null ? $user->id : 0,
            $organizationId,
            $input->name,
            $input->send_type,
            createdBySource: $consoleAuth->getSourceString(),
        );

        if ($user) {
            return $this->json(new ProjectUserObject($newProject['projectUser'], $user));
        }

        return $this->json(new ProjectObject($newProject['project']));
    }

    #[Route('/project', methods: 'GET')]
    #[ScopeRequired(RelayScope::PROJECT_READ)]
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
    #[ScopeRequired(RelayScope::PROJECT_WRITE)]
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
