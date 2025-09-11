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
        $results = $this->entityManager->getRepository(SendRecipient::class)
            ->createQueryBuilder('r')
            ->where('r.send = :send')
            ->andWhere('r.address LIKE :domain')
            ->setParameter('send', $sendAttempt->getSend())
            ->setParameter('domain', '%@' . $sendAttempt->getDomain())
            ->getQuery()
            ->getResult();

        return $results;
    }
}
