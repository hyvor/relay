<?php

namespace App\Service\Kyc;

use App\Entity\Kyc;
use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;
use App\Repository\KycRepository;
use App\Service\Kyc\Event\KycSubmittedEvent;
use App\Service\Kyc\Exception\KycAlreadyApprovedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class KycService
{
    use ClockAwareTrait;

    public function __construct(
        private KycRepository $kycRepository,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getByOrganizationId(int $organizationId): ?Kyc
    {
        return $this->kycRepository->findOneByOrganizationId($organizationId);
    }

    /**
     * @throws KycAlreadyApprovedException if the organization's KYC is already approved
     */
    public function submit(
        int $organizationId,
        string $fullName,
        KycBusinessType $businessType,
        ?string $businessName,
        string $country,
        string $address,
        string $phone,
        ?string $website
    ): Kyc {
        $kyc = $this->getByOrganizationId($organizationId);

        if ($kyc !== null && $kyc->getStatus() === KycStatus::APPROVED) {
            throw new KycAlreadyApprovedException('KYC for this organization has already been approved.');
        }

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
}
