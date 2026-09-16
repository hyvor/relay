<?php

namespace App\Api\Console\Controller\Org;

use App\Api\Console\Input\CreateProjectInput;
use App\Api\Console\Object\ProjectUserObject;
use App\Service\Project\ProjectService;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\OrgEndpoint;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ProjectsController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    #[Route('/projects', methods: 'POST')]
    #[OrgEndpoint]
    #[OA\Post(
        description: 'Creates a new project in the current organization and adds the authenticated user to it.',
        summary: 'Create a project'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the project-user object of the creator in the new project.',
        content: new Model(type: ProjectUserObject::class)
    )]
    public function create(
        #[MapRequestPayload] CreateProjectInput $input,
        ConsoleAuthResults $consoleAuth,
    ): JsonResponse {
        $organizationId = $consoleAuth->getOrganizationId();
        $user = $consoleAuth->getNullableUser();

        $newProject = $this->projectService->createProject(
            $organizationId,
            $input->name,
            $input->send_type,
            userId: $user?->id,
            createdBySource: $consoleAuth->getSourceString(),
        );

        return $this->json(new ProjectUserObject($newProject['projectUser'], $user));
    }
}
