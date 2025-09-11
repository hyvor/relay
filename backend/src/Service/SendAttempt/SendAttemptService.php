<?php

namespace App\Service\SendAttempt;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SuppressionReason;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Suppression\SuppressionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SendAttemptService
{

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private SendRecipientService $sendRecipientService,
        private SuppressionService $suppressionService,
    ) {
    }

    /**
     * @return SendAttempt[]
     */
    public function getSendAttemptsOfSend(Send $send): array
    {
        return $this->em->getRepository(SendAttempt::class)->findBy(['send' => $send], ['id' => 'DESC']);
    }

    public function getSendAttemptById(int $id): ?SendAttempt
    {
        return $this->em->getRepository(SendAttempt::class)->find($id);
    }

    /**
     * Send attempt was created from Go, then, /send-attempts/done was called
     * Now handle any side effects
     */
    public function handleAfterSendAttempt(SendAttempt $sendAttempt): void
    {
        if ($sendAttempt->getStatus() === SendAttemptStatus::BOUNCED) {
            $recipients = $this->sendRecipientService->getSendRecipientsBySendAttempt($sendAttempt);

            foreach ($recipients as $recipient) {
                $this->suppressionService->createSuppression(
                    $sendAttempt->getSend()->getProject(),
                    $recipient->getAddress(),
                    SuppressionReason::BOUNCE,
                    $sendAttempt->getError() ?? ''
                );
            }
        }

        $event = new Event\SendAttemptCreatedEvent($sendAttempt);
        $this->ed->dispatch($event);
    }

}
