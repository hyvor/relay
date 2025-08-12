<?php

namespace App\Service\Project;

use App\Api\Console\Authorization\Scope;
use App\Service\Instance\InstanceService;
use App\Service\ProjectUser\ProjectUserService;
use Hyvor\Internal\Sudo\SudoUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Hyvor\Internal\Sudo\Event\SudoAddedEvent;
use Hyvor\Internal\Sudo\Event\SudoRemovedEvent;

/**
 * While the system project is convenient, it needs maintenance
 * to keep it in sync with the system.
 */
#[AsEventListener(SudoAddedEvent::class, method: 'onSudoAdded')]
#[AsEventListener(SudoRemovedEvent::class, method: 'onSudoRemoved')]
class SystemProjectListener
{

    public function __construct(
        private SudoUserService $sudoUserService,
        private InstanceService $instanceService,
        private ProjectUserService $projectUserService,
    ) {
    }

    private function resetSystemProjectAccess(): void
    {
        $systemProject = $this->instanceService->getInstance()->getSystemProject();

        $this->projectUserService->deleteAllProjectUsers($systemProject);

        $allSudo = $this->sudoUserService->getAll();
        $scopes = [
            Scope::PROJECT_READ,
            Scope::SENDS_READ,
            Scope::DOMAINS_READ,
            Scope::ANALYTICS_READ,
        ];
        $scopes = array_map(fn($scope) => $scope->value, $scopes);

        foreach ($allSudo as $sudoUser) {
            $this->projectUserService->createProjectUser(
                $systemProject,
                $sudoUser->getUserId(),
                $scopes
            );
        }
    }

    public function onSudoAdded(SudoAddedEvent $event): void
    {
        $this->resetSystemProjectAccess();
    }

    public function onSudoRemoved(SudoRemovedEvent $event): void
    {
        $this->resetSystemProjectAccess();
    }

}