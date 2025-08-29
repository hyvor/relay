<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Service\Domain\Exception\DkimVerificationFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DomainStatusService
{

    use ClockAwareTrait;

    public function __construct(
        private DkimVerificationService $dkimVerificationService,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws DkimVerificationFailedException
     */
    public function updateAfterDkimVerification(
        Domain $domain,
        bool $unverifyWarning = false,
        bool $flush = false,
    ): void {
        assert(
            $domain->getStatus() !== DomainStatus::SUSPENDED,
            'You cannot run DKIM verification on a domain that is in SUSPENDED status.'
        );

        $dkimResult = $this->dkimVerificationService->verify($domain);

        // always updated
        $domain->setDkimCheckedAt($this->now());
        $domain->setDkimErrorMessage($dkimResult->errorMessage);

        $oldStatus = $domain->getStatus();
        $newStatus = $this->getNewStatusAfterDkimVerification($oldStatus, $dkimResult, $unverifyWarning);

        if ($newStatus !== $oldStatus) { // if status changed
            $domain->setStatus($newStatus);
            $domain->setStatusChangedAt($this->now());

            $this->eventDispatcher->dispatch(
                new DomainStatusChangedEvent(
                    $domain,
                    $oldStatus,
                    $newStatus,
                    $dkimResult
                )
            );
        }

        $this->em->persist($domain);
        if ($flush) {
            $this->em->flush();
        }
    }

    private function getNewStatusAfterDkimVerification(
        DomainStatus $domainStatus,
        DkimVerificationResult $dkimResult,
        bool $unverifyWarning
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
             * Warning -> Warning (if unverifyWarning is false) or Pending (if unverifyWarning is true)
             * Pending -> Pending
             */
            return match ($domainStatus) {
                DomainStatus::ACTIVE => DomainStatus::WARNING,
                // if unverifyWarning is true, then we set it to pending, otherwise it remains warning
                DomainStatus::WARNING => $unverifyWarning ? DomainStatus::PENDING : DomainStatus::WARNING,
                default => DomainStatus::PENDING
            };
        }
    }

}
