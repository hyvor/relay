<?php

namespace App\Service\Cloud;

use App\Entity\Queue;
use App\Entity\Type\KycStatus;
use App\Entity\Type\QueueType;
use App\Service\Cloud\Billing\LicenseService;
use App\Service\Kyc\KycService;
use App\Service\Queue\Event\ProjectQueueSelectingEvent;
use App\Service\Queue\QueueService;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\InternalConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Selects the queue of projects on Cloud.
 * Projects of organizations that are not production-eligible (paid + KYC approved)
 * are routed to the trial queue of the type. Other projects are distributed across
 * the remaining (production) queues of the type.
 *
 * Trial queues are set up manually in sudo.
 */
#[AsEventListener]
class CloudProjectQueueListener
{

    public const string TRIAL_QUEUE_NAME = 'trial';

    public function __construct(
        private InternalConfig $internalConfig,
        private LicenseService $licenseService,
        private KycService $kycService,
        private QueueService $queueService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProjectQueueSelectingEvent $event): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return;
        }

        $project = $event->getProject();
        $organizationId = $project->getOrganizationId();
        $type = QueueType::fromProjectSendType($project->getSendType());
        $queues = $this->queueService->getQueuesByType($type);

        // system project
        if ($organizationId === null || $organizationId === 0) {
            $event->setQueue($this->getProductionQueue($queues, $type, $project->getId()));
            return;
        }

        try {
            $license = $this->licenseService->getCachedLicense($organizationId);
        } catch (CommsApiFailedException $e) {
            $this->logger->error('Failed to fetch the license for queue selection', [
                'organizationId' => $organizationId,
                'exception' => $e,
            ]);

            // never fall through to production on errors
            // do not cache, so that the next send retries
            $event->setQueue($this->getTrialQueue($queues, $type));
            $event->setCacheable(false);
            return;
        }

        $event->setQueue(
            $this->isProductionEligible($organizationId, $license)
                ? $this->getProductionQueue($queues, $type, $project->getId())
                : $this->getTrialQueue($queues, $type)
        );
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

    /**
     * @param Queue[] $queues
     */
    private function getProductionQueue(array $queues, QueueType $type, int $projectId): Queue
    {
        $productionQueues = array_filter($queues, fn(Queue $queue) => !$this->isTrialQueue($queue));

        return $this->queueService->pickQueue($productionQueues, $projectId)
            ?? throw new \LogicException("No production queues found for type {$type->value}");
    }

    /**
     * @param Queue[] $queues
     */
    private function getTrialQueue(array $queues, QueueType $type): Queue
    {
        foreach ($queues as $queue) {
            if ($this->isTrialQueue($queue)) {
                return $queue;
            }
        }

        throw new \LogicException("Trial queue for type {$type->value} not found");
    }

    private function isTrialQueue(Queue $queue): bool
    {
        return $queue->getName() === self::TRIAL_QUEUE_NAME;
    }
}
