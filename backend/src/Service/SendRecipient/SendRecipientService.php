<?php

namespace App\Service\SendRecipient;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendAttemptRecipient;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Stats\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class SendRecipientService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StatsService $statsService,
    ) {}

    public function getSendRecipientByEmail(
        Send $send,
        string $email
    ): ?SendRecipient {
        return $this->em->getRepository(SendRecipient::class)
            ->findOneBy([
                'send' => $send,
                'address' => $email
            ]);
    }

    public function getSendRecipientByUuid(string $uuid): ?SendRecipient
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        return $this->em->getRepository(SendRecipient::class)->findOneBy(['uuid' => $uuid]);
    }

    public function getRecipientFromSendAndAttemptRecipient(
        Send $send,
        SendAttemptRecipient $attemptRecipient
    ): SendRecipient {
        $sendRecipients = $send->getRecipients();

        foreach ($sendRecipients as $sendRecipient) {
            if ($sendRecipient->getId() === $attemptRecipient->getSendRecipientId()) {
                return $sendRecipient;
            }
        }

        // @codeCoverageIgnoreStart
        throw new \RuntimeException(
            'SendRecipient not found for SendAttemptRecipient ID ' . $attemptRecipient->getId()
        );
        // @codeCoverageIgnoreEnd
    }

    public function updateSendRecipientStatus(
        SendRecipient $sendRecipient,
        SendRecipientStatus $status,
        ?BounceReason $bounceReason = null,
    ): void {
        $sendRecipient->setStatus($status);
        // the bounce reason is only kept while the recipient is bounced
        $sendRecipient->setBounceReason($status === SendRecipientStatus::BOUNCED ? $bounceReason : null);
        $this->em->persist($sendRecipient);
        $this->em->flush();

        // stats_project counts recipients by the day they were added
        $this->statsService->markDateOutdated($sendRecipient->getSend()->getCreatedAt());
    }
}
