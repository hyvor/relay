<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Object\ProjectObject;
use App\Service\Project\ProjectService;
use Hyvor\Internal\Bundle\Security\HasHyvorUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;


class ProjectController extends AbstractController
{

    use HasHyvorUser;

    public function __construct(
        private ProjectService $projectService
    ) {
    }

    #[Route('/project', methods: 'POST')]
    public function createProject(#[MapRequestPayload] CreateProjectInput $input): JsonResponse
    {
        $user = $this->getHyvorUser();

        $project = $this->projectService->createProject($user->id, $input->name);
        return $this->json(new ProjectObject($project));
    }
}