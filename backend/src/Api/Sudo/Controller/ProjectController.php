<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\Project\GetProjectOrganizationsInput;
use App\Api\Sudo\Input\Project\GetProjectsInput;
use App\Api\Sudo\Object\OrganizationObject;
use App\Api\Sudo\Object\ProjectObject;
use App\Entity\Project;
use App\Service\Project\ProjectService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Auth\Dto\Organization;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService,
        private AuthInterface $auth,
    ) {}

    #[Route('/projects', methods: 'GET')]
    public function getProjects(#[MapQueryString] GetProjectsInput $input): JsonResponse
    {
        $projects = $this->projectService->getProjects(
            $input->limit,
            $input->before_id,
            $input->search,
            $input->organization_id,
        );

        $organizations = $this->resolveOrganizations($projects);

        return $this->json([
            'projects' => array_map(
                fn(Project $project) => new ProjectObject($project),
                $projects,
            ),
            'orgs' => array_values(
                array_map(
                    fn(Organization $org) => new OrganizationObject($org),
                    $organizations,
                ),
            ),
        ]);
    }

    #[Route('/projects/organizations', methods: 'GET')]
    public function getProjectOrganizations(#[MapQueryString] GetProjectOrganizationsInput $input): JsonResponse
    {
        $orgIds = $this->projectService->getDistinctOrganizationIds($input->limit, $input->before_id);

        if ($orgIds === []) {
            return $this->json([]);
        }

        $organizations = $this->auth->organizations($orgIds, includeBillingInfo: true);

        // Preserve the id-descending order so the client can use the last id
        // as the cursor for the next page.
        $result = [];
        foreach ($orgIds as $orgId) {
            if (isset($organizations[$orgId])) {
                $result[] = new OrganizationObject($organizations[$orgId]);
            }
        }

        return $this->json($result);
    }

    #[Route('/projects/{id}', requirements: ['id' => Requirement::DIGITS], methods: 'GET')]
    public function getProject(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectById($id);

        if ($project === null) {
            throw new NotFoundHttpException("Project $id not found");
        }

        $organizations = $this->resolveOrganizations([$project]);
        $orgId = $project->getOrganizationId();
        $org = ($orgId !== null && isset($organizations[$orgId]))
            ? new OrganizationObject($organizations[$orgId])
            : null;

        return $this->json([
            'project' => new ProjectObject($project),
            'org' => $org,
        ]);
    }

    /**
     * Batch-resolves organizations for the given projects in a single internal
     * API call, avoiding N+1 lookups.
     *
     * @param Project[] $projects
     * @return array<int, Organization> indexed by organization id
     */
    private function resolveOrganizations(array $projects): array
    {
        $orgIds = [];
        foreach ($projects as $project) {
            $orgId = $project->getOrganizationId();
            if ($orgId !== null) {
                $orgIds[$orgId] = $orgId;
            }
        }

        if ($orgIds === []) {
            return [];
        }

        return $this->auth->organizations(array_values($orgIds), includeBillingInfo: true);
    }
}
