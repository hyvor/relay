<?php

namespace App\Service\Cloud;

use App\Entity\Queue;
use App\Entity\Type\KycStatus;
use App\Entity\Type\QueueType;
use App\Service\Kyc\KycService;
use App\Service\Queue\Event\ProjectQueueSelectingEvent;
use App\Service\Queue\QueueService;
use Hyvor\Internal\Billing\BillingInterface;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\InternalConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class CloudProjectQueueListener
{
    public function __construct(
        private InternalConfig $internalConfig,
        private BillingInterface $billing,
        private KycService $kycService,
        private QueueService $queueService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProjectQueueSelectingEvent $event): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return;
        }

        $project = $event->getProject();
        $organizationId = $project->getOrganizationId();

        // system project
        if ($organizationId === null || $organizationId === 0) {
            return;
        }

        $type = QueueType::fromProjectSendType($project->getSendType());

        try {
            $license = $event->getLicense() ?? $this->billing->license($organizationId);
        } catch (CommsApiFailedException $e) {
            $this->logger->error('Failed to fetch the license for queue selection', [
                'organizationId' => $organizationId,
                'exception' => $e,
            ]);

            // never fall through to production on errors
            // do not cache, so that the next send retries
            $event->setQueue($this->getTrialQueue($type));
            $event->setCacheable(false);
            return;
        }

        if ($this->isProductionEligible($organizationId, $license)) {
            // default selection
            return;
        }

        $event->setQueue($this->getTrialQueue($type));
    }

    private function isProductionEligible(int $organizationId, ResolvedLicense $license): bool
    {
        $isPaid = in_array(
            $license->type,
            [ResolvedLicenseType::SUBSCRIPTION, ResolvedLicenseType::ENTERPRISE_CONTRACT],
            true
        );

        if (!$isPaid) {
            return false;
        }

        $kyc = $this->kycService->getActiveByOrganizationId($organizationId);

        return $kyc?->getStatus() === KycStatus::APPROVED;
    }

    private function getTrialQueue(QueueType $type): Queue
    {
        $queue = $this->queueService->getTrialQueue($type);

        if ($queue === null) {
            throw new \LogicException("Trial queue for type {$type->value} not found");
        }

        return $queue;
    }

}
