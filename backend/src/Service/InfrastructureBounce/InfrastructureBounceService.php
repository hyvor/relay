<?php

namespace App\Service\InfrastructureBounce;

use App\Entity\InfrastructureBounce;
use Doctrine\ORM\EntityManagerInterface;

class InfrastructureBounceService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createInfrastructureBounce(
        int $sendRecipientId,
        int $smtpCode,
        string $smtpEnhancedCode,
        string $smtpMessage
    ): InfrastructureBounce {
        $infrastructureBounce = new InfrastructureBounce();
        $infrastructureBounce->setCreatedAt(new \DateTimeImmutable());
        $infrastructureBounce->setUpdatedAt(new \DateTimeImmutable());
        $infrastructureBounce->setIsRead(false);
        $infrastructureBounce->setSendRecipientId($sendRecipientId);
        $infrastructureBounce->setSmtpCode($smtpCode);
        $infrastructureBounce->setSmtpEnhancedCode($smtpEnhancedCode);
        $infrastructureBounce->setSmtpMessage($smtpMessage);

        $this->em->persist($infrastructureBounce);
        $this->em->flush();

        return $infrastructureBounce;
    }

    public function markAsRead(InfrastructureBounce $infrastructureBounce): void
    {
        $infrastructureBounce->setIsRead(true);
        $infrastructureBounce->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();
    }
}

