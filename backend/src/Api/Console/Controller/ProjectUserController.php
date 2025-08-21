<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\ProjectUser\CreateProjectUserInput;
use App\Api\Console\Object\ProjectUserObject;
use App\Api\Console\Object\ProjectUserSearchObject;
use App\Entity\Project;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Auth\AuthInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProjectUserController extends AbstractController
{
    public function __construct(
        private ProjectUserService $projectUserService,
        private AuthInterface $auth,
    ) {
    }

    #[Route('/search-users', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    public function searchUsers(Request $request): JsonResponse
    {
        $emailSearch = $request->query->getString('email', '');
        $authUsers = $this->auth->fromEmail($emailSearch);
        $foundUsers = [];
        foreach ($authUsers as $authUser) {
            $foundUsers[] = new ProjectUserSearchObject($authUser);
        }
        return $this->json($foundUsers);
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
        $projectUser = $this->projectUserService->createProjectUser($project, $authUser->id, $input->scopes);

        return $this->json(new ProjectUserObject(($projectUser), $authUser));
    }
}
