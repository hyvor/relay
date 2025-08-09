<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DomainStatusService
{

    use ClockAwareTrait;

    public function __construct(
        private DkimVerificationService $dkimVerificationService,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws DkimVerificationFailedException
     */
    public function updateAfterDkimVerification(Domain $domain): void
    {
        assert(
            $domain->getStatus() !== DomainStatus::SUSPENDED,
            'You cannot run DKIM verification on a domain that is in SUSPENDED status.'
        );

        $dkimResult = $this->dkimVerificationService->verify($domain);

        // always updated
        $domain->setDkimCheckedAt($this->now());
        $domain->setDkimErrorMessage($dkimResult->errorMessage);

        $oldStatus = $domain->getStatus();
        $newStatus = $this->getNewStatusAfterDkimVerification($oldStatus, $dkimResult);

        if ($newStatus !== $oldStatus) { // if status changed
            $domain->setStatus($newStatus);
            $domain->setStatusChangedAt($this->now());

            $this->eventDispatcher->dispatch(new DomainStatusChangedEvent($domain, $dkimResult));
        }
    }

    private function getNewStatusAfterDkimVerification(
        DomainStatus $domainStatus,
        DkimVerificationResult $dkimResult
    ): DomainStatus {
        if ($dkimResult->verified) {
            /**
             * Pending -> Active
             * Warning -> Active
             * Active -> Active
             */
            return DomainStatus::ACTIVE;
        } else {
            /**
             * Active -> Warning
             * Warning -> Pending
             * Pending -> Pending
             */
            return match ($domainStatus) {
                DomainStatus::ACTIVE => DomainStatus::WARNING,
                default => DomainStatus::PENDING
            };
        }
    }

}