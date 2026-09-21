<?php

namespace App\Service\Kyc;

use App\Entity\Kyc;
use App\Entity\Type\KycAccountType;
use App\Entity\Type\KycStatus;
use App\Service\Kyc\Exception\KycNotPendingException;
use App\Service\Kyc\Exception\PaymentMethodRequiredException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Billing\BillingInterface;
use Hyvor\Internal\Billing\License\RelayLicense;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicenseType;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscription;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\GetOrganizations;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\Component\Component;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class KycService
{
    use ClockAwareTrait;

    // the plan a KYC-approved organization is automatically subscribed to
    public const string MINIMUM_PLAN = 'starter';

    public const array SORTABLE_COLUMNS = ['status', 'created_at'];
    public const array SORT_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private CommsInterface $comms,
        private LoggerInterface $logger,
        private BillingInterface $billing,
    ) {
    }

    public function getCurrentByOrganizationId(int $organizationId): ?Kyc
    {
        return $this->getPendingByOrganizationId($organizationId)
            ?? $this->getActiveByOrganizationId($organizationId);
    }

    public function getPendingByOrganizationId(int $organizationId, bool $lock = false): ?Kyc
    {
        $query = $this->em->getRepository(Kyc::class)
            ->createQueryBuilder('k')
            ->andWhere('k.organization_id = :organizationId')
            ->andWhere('k.status = :pending')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('pending', KycStatus::PENDING)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var Kyc|null $result */
        $result = $query->getOneOrNullResult();

        return $result;
    }

    public function getActiveByOrganizationId(int $organizationId): ?Kyc
    {
        /** @var Kyc|null $result */
        $result = $this->em->getRepository(Kyc::class)
            ->createQueryBuilder('k')
            ->andWhere('k.organization_id = :organizationId')
            ->andWhere('k.status IN (:active)')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('active', [KycStatus::APPROVED, KycStatus::REJECTED])
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function getById(int $id): ?Kyc
    {
        return $this->em->getRepository(Kyc::class)->find($id);
    }

    /**
     * @return Kyc[]
     */
    public function listAll(
        ?KycStatus $status,
        ?int $organizationId = null,
        string $sortBy = 'created_at',
        string $sort = 'desc',
        int $limit = 30,
        int $offset = 0,
    ): array {
        assert(in_array($sortBy, self::SORTABLE_COLUMNS, true));
        assert(in_array($sort, self::SORT_DIRECTIONS, true));

        $qb = $this->em->getRepository(Kyc::class)->createQueryBuilder('k');

        if ($organizationId !== null) {
            $qb->andWhere('k.organization_id = :organizationId')
                ->setParameter('organizationId', $organizationId);
        }

        if ($status !== null) {
            $qb->andWhere('k.status = :status')
                ->setParameter('status', $status);
        } elseif ($organizationId === null) {
            $qb->andWhere('k.status != :stale')
                ->setParameter('stale', KycStatus::STALE);
        }

        $qb->orderBy('k.' . $sortBy, $sort)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var Kyc[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $contentOwnership
     * @throws PaymentMethodRequiredException if the organization has no payment method added
     */
    public function submit(
        int $organizationId,
        KycAccountType $accountType,
        string $name,
        string $country,
        string $address,
        string $website,
        string $email,
        array $contentOwnership,
        bool $sendingTransactional,
        bool $sendingDistributional,
        string $useCase,
    ): Kyc {
        $this->assertHasPaymentMethod($organizationId);

        return $this->em->wrapInTransaction(function () use (
            $organizationId,
            $accountType,
            $name,
            $country,
            $address,
            $website,
            $email,
            $contentOwnership,
            $sendingTransactional,
            $sendingDistributional,
            $useCase,
        ) {
            $pending = $this->getPendingByOrganizationId($organizationId, lock: true);

            $kyc = $pending ?? new Kyc();

            if ($pending === null) {
                $kyc->setCreatedAt($this->now());
                $kyc->setOrganizationId($organizationId);
                $kyc->setStatus(KycStatus::PENDING);
            }

            $kyc->setUpdatedAt($this->now());
            $kyc->setAccountType($accountType);
            $kyc->setName($name);
            $kyc->setCountry($country);
            $kyc->setAddress($address);
            $kyc->setWebsite($website);
            $kyc->setEmail($email);
            $kyc->setContentOwnership($contentOwnership);
            $kyc->setSendingTransactional($sendingTransactional);
            $kyc->setSendingDistributional($sendingDistributional);
            $kyc->setUseCase($useCase);

            $this->em->persist($kyc);
            $this->em->flush();

            return $kyc;
        });
    }

    /**
     * @throws PaymentMethodRequiredException
     */
    private function assertHasPaymentMethod(int $organizationId): void
    {
        try {
            $response = $this->comms->send(new GetOrganizations([$organizationId], includeBillingInfo: true));
        } catch (CommsApiFailedException $e) {
            throw new PaymentMethodRequiredException(
                'Unable to verify your payment method right now. Please try again.',
            );
        }

        $organization = $response->getOrganizations()[$organizationId] ?? null;

        if ($organization === null || !$organization->hasPaymentMethod()) {
            throw new PaymentMethodRequiredException(
                'Please add a payment method before submitting your KYC.',
            );
        }
    }

    /**
     * Approves the KYC and charges the organization's payment method for the
     * minimum (starter) subscription. The approval itself always goes through
     * even if the charge fails.
     *
     * @throws KycNotPendingException
     */
    public function approve(Kyc $kyc, ?string $note = null): Kyc
    {
        $kyc = $this->em->wrapInTransaction(function () use ($kyc, $note) {

            $lockedKyc = $this->em->find(Kyc::class, $kyc->getId(), LockMode::PESSIMISTIC_WRITE);
            assert($lockedKyc !== null);

            if ($lockedKyc->getStatus() !== KycStatus::PENDING) {
                throw new KycNotPendingException('Only pending KYC submissions can be approved.');
            }

            $this->staleActiveRecord($lockedKyc->getOrganizationId());

            $lockedKyc->setStatus(KycStatus::APPROVED);
            $lockedKyc->setUpdatedAt($this->now());
            $lockedKyc->setNote($note);

            $this->em->persist($lockedKyc);
            $this->em->flush();

            return $lockedKyc;
        });

        $resolvedLicense = $this->billing->license($kyc->getOrganizationId());
        $license = $resolvedLicense->license;

        if ($resolvedLicense->type !== ResolvedLicenseType::TRIAL && $license instanceof RelayLicense) {
            return $kyc;
        }

        try {
            $this->comms->send(
                new CreateSubscription($kyc->getOrganizationId(), Component::RELAY, self::MINIMUM_PLAN),
            );
        } catch (CommsApiFailedException $e) {
            $this->logger->error(
                'Failed to create subscription for organization ' . $kyc->getOrganizationId(),
                [
                    'exception' => $e,
                    'organizationId' => $kyc->getOrganizationId(),
                ]
            );
        }

        return $kyc;
    }

    /**
     * @throws KycNotPendingException
     */
    public function reject(Kyc $kyc, ?string $note = null, ?string $rejectReason = null): Kyc
    {
        return $this->em->wrapInTransaction(function () use ($kyc, $note, $rejectReason) {

            $lockedKyc = $this->em->find(Kyc::class, $kyc->getId(), LockMode::PESSIMISTIC_WRITE);
            assert($lockedKyc !== null);

            if ($lockedKyc->getStatus() !== KycStatus::PENDING) {
                throw new KycNotPendingException('Only pending KYC submissions can be rejected.');
            }

            $this->staleActiveRecord($lockedKyc->getOrganizationId());

            $lockedKyc->setStatus(KycStatus::REJECTED);
            $lockedKyc->setUpdatedAt($this->now());
            $lockedKyc->setNote($note);
            $lockedKyc->setRejectReason($rejectReason);

            $this->em->persist($lockedKyc);
            $this->em->flush();

            return $lockedKyc;
        });
    }

    private function staleActiveRecord(int $organizationId): void
    {
        $active = $this->getActiveByOrganizationId($organizationId);

        if ($active !== null) {
            $active->setStatus(KycStatus::STALE);
            $active->setUpdatedAt($this->now());
            $this->em->persist($active);
        }
    }
}
