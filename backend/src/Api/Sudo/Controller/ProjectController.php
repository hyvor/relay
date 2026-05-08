<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\ProjectObject;
use App\Service\Project\ProjectService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    #[Route('/project/{id}', methods: 'GET', requirements: ['id' => '\d+'])]
    public function getProject(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectByIdIncludingDeleted($id);
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $this->json(new ProjectObject($project));
    }

    #[Route('/project/{id}/undelete', methods: 'POST', requirements: ['id' => '\d+'])]
    public function undeleteProject(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectByIdIncludingDeleted($id);
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        $this->projectService->undeleteProject($project);

        return $this->json(new ProjectObject($project));
    }
}
