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
use Hyvor\Internal\Deployment;
use Hyvor\Internal\InternalConfig;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProjectUsersController extends AbstractController
{
    public function __construct(
        private ProjectUserService $projectUserService,
        private AuthInterface $auth,
        private CommsInterface $comms,
        private InternalConfig $internalConfig,
    ) {
    }

    #[Route('/project-users', methods: 'GET')]
    #[ScopeRequired(Scope::PROJECT_READ)]
    #[OA\Get(
        summary: 'Get all project users',
        description: 'Returns all users that have access to the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of project users',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: ProjectUserObject::class))
        )
    )]
    public function list(Project $project): JsonResponse
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
    #[OA\Post(
        summary: 'Add a user to the project',
        description: 'Adds an existing user to the project with the given scopes.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the created project-user object.',
        content: new Model(type: ProjectUserObject::class)
    )]
    public function create(
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

        if ($this->internalConfig->getDeployment() === Deployment::CLOUD) {
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
        }

        $projectUser = $this->projectUserService->createProjectUser($project, $authUser->id, $input->scopes);

        return $this->json(new ProjectUserObject($projectUser, $authUser));
    }

    #[Route('/project-users/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    #[OA\Delete(
        summary: 'Remove a user from the project',
        description: 'Removes a single user from the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty object on success.',
        content: new OA\JsonContent()
    )]
    public function delete(ProjectUser $projectUser): JsonResponse
    {
        $this->projectUserService->deleteProjectUser($projectUser);
        return $this->json([]);
    }

    #[Route('/project-users', methods: 'DELETE')]
    #[ScopeRequired(Scope::PROJECT_WRITE)]
    #[OA\Delete(
        summary: 'Remove all users from the project',
        description: 'Removes all project users except the owner.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty object on success.',
        content: new OA\JsonContent()
    )]
    public function deleteAll(Project $project): JsonResponse
    {
        $this->projectUserService->deleteAllProjectUsers($project);
        return $this->json([]);
    }
}
