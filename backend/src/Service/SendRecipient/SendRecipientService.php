<?php

namespace App\Service\SendRecipient;

use App\Entity\SendAttempt;
use App\Entity\SendFeedback;
use App\Entity\SendRecipient;
use Doctrine\ORM\EntityManagerInterface;

class SendRecipientService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSendRecipientByEmail(string $email): ?SendRecipient
    {
        return $this->entityManager->getRepository(SendFeedback::class)
            ->findOneBy(['email' => $email]);
    }

    /**
     * @return SendRecipient[]
     */
    public function getSendRecipientsBySendAttempt(SendAttempt $sendAttempt): array
    {
        return $this->entityManager->getRepository(SendRecipient::class)
            ->createQueryBuilder('r')
            ->where('r.address LIKE :domain')
            ->andWhere('r.send = :send')
            ->setParameter('domain', '%@' . $sendAttempt->getDomain())
            ->setParameter('send', $sendAttempt->getSend())
            ->getQuery()
            ->getResult();
    }
}
