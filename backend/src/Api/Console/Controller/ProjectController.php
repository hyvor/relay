<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\ProjectService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ScopeRequired;
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
    #[ScopeRequired(Scope::ORG_PROJECTS_CREATE)]
    public function createProject(
        #[MapRequestPayload] CreateProjectInput $input,
        ConsoleAuthResults $consoleAuth,
    ): JsonResponse {
        $newProject = $this->projectService->createProject(
            $consoleAuth->getNullableUser()?->id ?? 0,
            $consoleAuth->getOrganizationId(),
            $input->name,
            $input->send_type,
            createdBySource: $consoleAuth->getSourceString(),
        );

        $user = $consoleAuth->getNullableUser();

        if ($user) {
            return $this->json(new ProjectUserObject($newProject['projectUser'], $user));
        }

        return $this->json(new ProjectObject($newProject['project']));
    }

    #[Route('/project', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_READ)]
    public function getNewsletterById(Project $project): JsonResponse
    {
        return $this->json(new ProjectObject($project));
    }

    #[Route('/project', methods: 'PATCH')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function updateProject(#[MapRequestPayload] UpdateProjectInput $input, Project $project): JsonResponse
    {
        $updates = new UpdateProjectDto();

        if ($input->hasProperty('name')) {
            $updates->name = $input->name;
        }

        $updatedProject = $this->projectService->updateProject($project, $updates);

        return $this->json(new ProjectObject($updatedProject));
    }
}
