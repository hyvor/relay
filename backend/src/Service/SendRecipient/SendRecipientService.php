<?php

namespace App\Service\SendRecipient;

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
}
