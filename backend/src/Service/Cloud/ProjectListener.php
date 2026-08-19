<?php

namespace App\Service\Cloud;

use Hyvor\Internal\CloudApi\ConsoleApiAuth\AccessType;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleApiAuthorizationListenerAbstract;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use App\Service\Project\Event\ProjectCreatingEvent;
use Hyvor\Internal\InternalConfig;
use Hyvor\Internal\Sudo\SudoUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @codeCoverageIgnore
 * Code coverage ignored since this is temporary
 * Other Cloud code should be covered
 */
#[AsEventListener(event: ProjectCreatingEvent::class, method: 'onProjectCreation')]
class ProjectListener
{
    public function __construct(
        private RequestStack $requestStack,
        private SudoUserService $sudoUserService,
        private InternalConfig $internalConfig,
    ) {
    }

    public function onProjectCreation(ProjectCreatingEvent $event): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api/console')) {
            return;
        }

        $consoleAuthResults = $request->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);

        if (!$consoleAuthResults instanceof ConsoleAuthResults) {
            return;
        }

        if ($consoleAuthResults->getAccessType() !== AccessType::SESSION) {
            return;
        }

        $user = $consoleAuthResults->getNullableUser();

        if ($user === null) {
            return;
        }

		$isSudo = $this->sudoUserService->exists($user->id);

        if (!$isSudo) {
            throw new BadRequestHttpException('Currently not available for public usage.');
        }
    }

}
