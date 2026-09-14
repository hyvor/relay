<?php

namespace App\Service\Kyc;

use App\Entity\Kyc;
use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;
use App\Repository\KycRepository;
use App\Service\Kyc\Event\KycSubmittedEvent;
use App\Service\Kyc\Exception\KycAlreadyApprovedException;
use App\Service\Kyc\Exception\PaymentMethodRequiredException;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Organization\GetOrganizations;
use Hyvor\Internal\Bundle\Comms\Exception\CommsApiFailedException;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class KycService
{
    use ClockAwareTrait;

    public function __construct(
        private KycRepository $kycRepository,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher,
        private CommsInterface $comms,
    ) {
    }

    public function getByOrganizationId(int $organizationId): ?Kyc
    {
        return $this->kycRepository->findOneByOrganizationId($organizationId);
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
}
