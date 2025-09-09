<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Authorization\UserLevelEndpoint;
use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Input\UpdateProjectInput;
use App\Api\Console\Object\ProjectObject;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;


class ProjectController extends AbstractController
{

    public function __construct(
        private ProjectService $projectService
    ) {
    }

    #[Route('/project', methods: 'POST')]
    #[UserLevelEndpoint]
    public function createProject(#[MapRequestPayload] CreateProjectInput $input, Request $request): JsonResponse
    {
        $user = AuthorizationListener::getUser($request);

        $newProject = $this->projectService->createProject(
            $user->id,
            $input->name,
            $input->send_type
        );

        return $this->json(new ProjectUserObject($newProject['projectUser'], $user));
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
