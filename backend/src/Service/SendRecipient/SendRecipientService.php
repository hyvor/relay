<?php

namespace App\Service\SendRecipient;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendAttemptRecipient;
use App\Entity\SendRecipient;
use Doctrine\ORM\EntityManagerInterface;

class SendRecipientService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getSendRecipientByEmail(
        Send $send,
        string $email
    ): ?SendRecipient {
        return $this->entityManager->getRepository(SendRecipient::class)
            ->findOneBy([
                'send' => $send,
                'address' => $email
            ]);
    }

    /**
     * @return SendRecipient[]
     * @deprecated
     */
    public function getSendRecipientsBySendAttempt(SendAttempt $sendAttempt): array
    {
        $recipients = $sendAttempt->getRecipients();
        $recipientIds = $recipients->map(static fn(SendAttemptRecipient $recipient) => $recipient->getId())->toArray();

        /** @var SendRecipient[] $results */
        $results = $this->entityManager->getRepository(SendRecipient::class)
            ->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $recipientIds)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
