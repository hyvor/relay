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

    public function getRecipientFromSendAndAttemptRecipient(
        Send $send,
        SendAttemptRecipient $attemptRecipient
    ): ?SendRecipient {
        $sendRecipients = $send->getRecipients();

        foreach ($sendRecipients as $sendRecipient) {
            if ($sendRecipient->getId() === $attemptRecipient->getSendRecipientId()) {
                return $sendRecipient;
            }
        }

        return null;
    }

}
