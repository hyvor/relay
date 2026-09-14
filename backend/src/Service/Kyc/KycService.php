<?php

namespace App\Service\Kyc;

use App\Entity\Kyc;
use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;
use App\Repository\KycRepository;
use App\Service\Kyc\Dto\KycApprovalResult;
use App\Service\Kyc\Event\KycApprovedEvent;
use App\Service\Kyc\Event\KycRejectedEvent;
use App\Service\Kyc\Event\KycSubmittedEvent;
use App\Service\Kyc\Exception\KycAlreadyApprovedException;
use App\Service\Kyc\Exception\KycNotPendingException;
use App\Service\Kyc\Exception\PaymentMethodRequiredException;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Billing\CreateSubscription;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\GetOrganizations;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Hyvor\Internal\Component\Component;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class KycService
{
    use ClockAwareTrait;

    // the plan a KYC-approved organization is automatically subscribed to
    public const string MINIMUM_PLAN = 'starter';

    public const array SORTABLE_COLUMNS = ['status', 'submitted_at', 'created_at'];
    public const array SORT_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private KycRepository $kycRepository,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private CommsInterface $comms,
    ) {
    }

    public function getByOrganizationId(int $organizationId): ?Kyc
    {
        return $this->kycRepository->findOneBy(['organization_id' => $organizationId]);
    }

    public function getById(int $id): ?Kyc
    {
        return $this->kycRepository->find($id);
    }

    /**
     * @return Kyc[]
     */
    public function listAll(
        ?KycStatus $status,
        string $sortBy = 'submitted_at',
        string $sort = 'desc',
        int $limit = 30,
        int $offset = 0,
    ): array {
        if (!in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            throw new \InvalidArgumentException("Cannot sort by '$sortBy'.");
        }

        if (!in_array($sort, self::SORT_DIRECTIONS, true)) {
            throw new \InvalidArgumentException("Invalid sort direction '$sort'.");
        }

        $qb = $this->kycRepository->createQueryBuilder('k');

        if ($status !== null) {
            $qb->andWhere('k.status = :status')->setParameter('status', $status);
        }

        $qb
            ->orderBy('k.' . $sortBy, $sort)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        /** @var Kyc[] */
        return $qb->getQuery()->getResult();
    }

    public function countAll(?KycStatus $status): int
    {
        $qb = $this->kycRepository->createQueryBuilder('k')->select('COUNT(k.id)');

        if ($status !== null) {
            $qb->andWhere('k.status = :status')->setParameter('status', $status);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @throws KycAlreadyApprovedException if the organization's KYC is already approved
     * @throws PaymentMethodRequiredException if the organization has no payment method on file
     */
    public function submit(
        int $organizationId,
        string $fullName,
        KycBusinessType $businessType,
        ?string $businessName,
        string $country,
        string $address,
        string $phone,
        string $website
    ): Kyc {
        $kyc = $this->getByOrganizationId($organizationId);

        if ($kyc !== null && $kyc->getStatus() === KycStatus::APPROVED) {
            throw new KycAlreadyApprovedException('KYC for this organization has already been approved.');
        }

        $this->assertHasPaymentMethod($organizationId);

        if ($kyc === null) {
            $kyc = new Kyc();
            $kyc->setCreatedAt($this->now());
            $kyc->setOrganizationId($organizationId);
        }

        $kyc->setUpdatedAt($this->now());
        $kyc->setFullName($fullName);
        $kyc->setBusinessType($businessType);
        $kyc->setBusinessName($businessName);
        $kyc->setCountry($country);
        $kyc->setAddress($address);
        $kyc->setPhone($phone);
        $kyc->setWebsite($website);
        $kyc->setStatus(KycStatus::PENDING);
        $kyc->setSubmittedAt($this->now());

        $this->em->persist($kyc);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new KycSubmittedEvent($kyc));

        return $kyc;
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
    public function approve(Kyc $kyc): KycApprovalResult
    {
        if ($kyc->getStatus() !== KycStatus::PENDING) {
            throw new KycNotPendingException('Only pending KYC submissions can be approved.');
        }

        $kyc->setStatus(KycStatus::APPROVED);
        $kyc->setUpdatedAt($this->now());

        $this->em->persist($kyc);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new KycApprovedEvent($kyc));

        [$chargeSuccess, $chargeError] = $this->chargeMinimumSubscription($kyc->getOrganizationId());

        return new KycApprovalResult($kyc, $chargeSuccess, $chargeError);
    }

    /**
     * @throws KycNotPendingException
     */
    public function reject(Kyc $kyc): Kyc
    {
        if ($kyc->getStatus() !== KycStatus::PENDING) {
            throw new KycNotPendingException('Only pending KYC submissions can be rejected.');
        }

        $kyc->setStatus(KycStatus::REJECTED);
        $kyc->setUpdatedAt($this->now());

        $this->em->persist($kyc);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new KycRejectedEvent($kyc));

        return $kyc;
    }

    /**
     * @return array{0: bool, 1: ?string} [success, errorMessage]
     */
    private function chargeMinimumSubscription(int $organizationId): array
    {
        try {
            $response = $this->comms->send(
                new CreateSubscription($organizationId, Component::RELAY, self::MINIMUM_PLAN),
            );
        } catch (CommsApiFailedException $e) {
            return [false, 'Unable to reach the billing service: ' . $e->getMessage()];
        }

        return [$response->isSuccess(), $response->getErrorMessage()];
    }
}
