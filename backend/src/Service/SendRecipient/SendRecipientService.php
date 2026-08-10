<?php

namespace App\Service\SendRecipient;

use App\Entity\Send;
use App\Entity\SendAttemptRecipient;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendRecipientStatus;
use Doctrine\ORM\EntityManagerInterface;

class SendRecipientService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function getSendRecipientByEmail(
        Send $send,
        string $email,
    ): ?SendRecipient {
        return $this->em
            ->getRepository(SendRecipient::class)
            ->findOneBy([
                'send' => $send,
                'address' => $email,
            ]);
    }

    public function getRecipientFromSendAndAttemptRecipient(
        Send $send,
        SendAttemptRecipient $attemptRecipient,
    ): SendRecipient {
        $sendRecipients = $send->getRecipients();

        foreach ($sendRecipients as $sendRecipient) {
            if ($sendRecipient->getId() === $attemptRecipient->getSendRecipientId()) {
                return $sendRecipient;
            }
        }

        // @codeCoverageIgnoreStart
        throw new \RuntimeException(
            'SendRecipient not found for SendAttemptRecipient ID ' . $attemptRecipient->getId(),
        );
        // @codeCoverageIgnoreEnd
    }

    public function updateSendRecipientStatus(
        SendRecipient $sendRecipient,
        SendRecipientStatus $status,
        ?BounceReason $bounceReason = null,
    ): void {
        $sendRecipient->setStatus($status);
        if ($bounceReason !== null) {
            $sendRecipient->setBouncedReason($bounceReason);
        }
        $this->em->persist($sendRecipient);
        $this->em->flush();
    }

}
