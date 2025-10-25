<?php

namespace App\Service\Project;

use App\Api\Console\Authorization\Scope;
use App\Entity\Domain;
use App\Repository\DomainRepository;
use App\Service\Domain\DomainService;
use App\Service\Domain\Dto\UpdateDomainDto;
use App\Service\Instance\Event\InstanceUpdatedEvent;
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
#[AsEventListener(InstanceUpdatedEvent::class, method: 'onInstanceUpdated')]
class SystemProjectListener
{

    public function __construct(
        private SudoUserService $sudoUserService,
        private InstanceService $instanceService,
        private ProjectUserService $projectUserService,
        private DomainRepository $domainRepository,
        private DomainService $domainService,
    ) {
    }

    private function resetSystemProjectAccess(): void
    {
        $systemProject = $this->instanceService->getInstance()->getSystemProject();
        $this->projectUserService->deleteAllProjectUsers($systemProject);

        // get system project again to avoid delete-related bugs in em
        $systemProject = $this->instanceService->getInstance()->getSystemProject();
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

    private function getSystemProjectDomain(): Domain
    {
        $systemProject = $this->instanceService->getInstance()->getSystemProject();
        $domain = $this->domainRepository->findOneBy(['project' => $systemProject]);

        if (!$domain) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('System project does not have a domain. This should not happen.');
            // @codeCoverageIgnoreEnd
        }

        return $domain;
    }

    public function onInstanceUpdated(InstanceUpdatedEvent $event): void
    {
        if ($event->getUpdates()->domainSet) {
            $systemProjectDomain = $this->getSystemProjectDomain();

            $updates = new UpdateDomainDto();
            $updates->domain = $event->getNewInstance()->getDomain();
            $updates->status = $systemProjectDomain->getStatus();

            $this->domainService->updateDomain($systemProjectDomain, $updates);
        }
    }

}
