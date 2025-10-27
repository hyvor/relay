<?php

namespace App\Service\SendAttempt;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SuppressionReason;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Smtp\SmtpResponseParser;
use App\Service\Suppression\SuppressionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SendAttemptService
{

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private SuppressionService $suppressionService,
        private SendRecipientService $sendRecipientService,
        private InfrastructureBounceService $infrastructureBounceService,
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
     * Now handle any side effects such as suppressions
     */
    public function handleAfterSendAttempt(SendAttempt $sendAttempt): void
    {
        foreach ($sendAttempt->getRecipients() as $attemptRecipient) {
            $parser = SmtpResponseParser::fromAttemptRecipient($attemptRecipient);
            $sendRecipient = $this->sendRecipientService->getRecipientFromSendAndAttemptRecipient(
                $sendAttempt->getSend(),
                $attemptRecipient
            );

            if ($parser->isRecipientBounce()) {
                $this->suppressionService->createSuppression(
                    $sendAttempt->getSend()->getProject(),
                    $sendRecipient->getAddress(),
                    SuppressionReason::BOUNCE,
                    $parser->getFullMessage()
                );
                $this->infrastructureBounceService->createInfrastructureBounce(
                    $attemptRecipient->getSendRecipientId(),
                    $attemptRecipient->getSmtpCode(),
                    $attemptRecipient->getSmtpEnhancedCode() ?? '',
                    $attemptRecipient->getSmtpMessage()
                );
            }
        }

        $event = new Event\SendAttemptCreatedEvent($sendAttempt);
        $this->ed->dispatch($event);
    }

}
