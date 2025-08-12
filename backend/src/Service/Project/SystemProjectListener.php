<?php

namespace App\Service\Project;

use App\Service\Instance\InstanceService;
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
    ) {
    }

    private function resetSystemProjectAccess(): void
    {
        $systemProject = $this->instanceService->getInstance()->getSystemProject();
        //
    }

    public function onSudoAdded(SudoAddedEvent $event): void
    {
    }

}