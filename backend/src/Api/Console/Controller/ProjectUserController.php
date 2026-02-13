<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\ProjectUser\CreateProjectUserInput;
use App\Api\Console\Object\ProjectUserObject;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Auth\AuthInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\VerifyMember;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProjectUserController extends AbstractController
{
    public function __construct(
        private ProjectUserService $projectUserService,
		private AuthInterface $auth,
		private CommsInterface $comms,
    ) {
    }

    #[Route('/project-users', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_READ)]
    public function getProjectUsers(Project $project): JsonResponse
    {
        $projectUsers = $this->projectUserService->getProjectUsers($project);

        $projectUsersById = [];
        foreach ($projectUsers as $projectUser) {
            $projectUsersById[$projectUser->getUserId()] = $projectUser;
        }

        $authUsers = $this->auth->fromIds(array_keys($projectUsersById));

        return $this->json(array_map(
            fn($authUser) => new ProjectUserObject(
                $projectUsersById[$authUser->id],
                $authUser
            ),
            array_values($authUsers)
        ));
    }

    #[Route('/project-users', methods: 'POST')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function addProjectUser(
        Project $project,
        #[MapRequestPayload] CreateProjectUserInput $input): JsonResponse
    {
        $authUser = $this->auth->fromId($input->user_id);
        if ($authUser === null) {
            throw new NotFoundHttpException('User with id ' . $input->user_id . ' not found.');
		}

        if ($this->projectUserService->getProjectUser($project, $authUser->id) !== null) {
            throw new BadRequestHttpException('User is already added to the project');
        }

		$organizationId = $project->getOrganizationId();
		assert($organizationId !== null);

		try {
			$verification = $this->comms->send(
				new VerifyMember(
					$organizationId,
					$authUser->id
				),
			);
		} catch (CommsApiFailedException $e) {
			throw new BadRequestHttpException('Unable to verify the user.');
		}

		if (!$verification->isMember()) {
			  throw new BadRequestHttpException('Unable to find the user in the organization');
		}

        $projectUser = $this->projectUserService->createProjectUser($project, $authUser->id, $input->scopes);

        return $this->json(new ProjectUserObject($projectUser, $authUser));
    }

    #[Route('/project-users/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function deleteProjectUser(ProjectUser $projectUser): JsonResponse
    {
        $this->projectUserService->deleteProjectUser($projectUser);
        return $this->json([]);
    }

    #[Route('/project-users', methods: 'DELETE')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function deleteAllProjectUsers(Project $project): JsonResponse
    {
        $this->projectUserService->deleteAllProjectUsers($project);
        return $this->json([]);
    }
}
