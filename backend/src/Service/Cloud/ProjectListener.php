<?php

namespace App\Service\Cloud;

use App\Api\Console\Authorization\AuthorizationListener;
use App\Service\Project\Event\ProjectCreatingEvent;
use Hyvor\Internal\Sudo\SudoUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsEventListener(event: ProjectCreatingEvent::class, method: 'onProjectCreation')]
class ProjectListener
{
    public function __construct(
        private RequestStack $requestStack,
        private SudoUserService $sudoUserService,
    ) {
    }

    public function onProjectCreation(ProjectCreatingEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api/console')) {
            return;
        }

        if (!AuthorizationListener::hasUser($request)) {
            return;
        }

        $user = AuthorizationListener::getUser($request);
        $isSudo = $this->sudoUserService->exists($user->id);

        if (!$isSudo) {
            throw new BadRequestHttpException('Currently not available for public usage.');
        }
    }

}
