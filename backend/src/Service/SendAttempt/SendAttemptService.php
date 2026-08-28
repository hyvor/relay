<?php

namespace App\Service\SendAttempt;

use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendAttemptStatus;
use App\Entity\Type\SuppressionReason;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Service\SendRecipient\SendRecipientService;
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
            $sendRecipient = $this->sendRecipientService->getRecipientFromSendAndAttemptRecipient(
                $sendAttempt->getSend(),
                $attemptRecipient
            );

            if ($attemptRecipient->getBouncedReason() === BounceReason::RECIPIENT) {
                $description = $attemptRecipient->getSmtpMessage();
                $code = $attemptRecipient->getSmtpCode();
                $enhancedCode = $attemptRecipient->getSmtpEnhancedCode();
                if ($code !== 0) {
                    $description = $code . ($enhancedCode ? ' ' . $enhancedCode : '') . ' ' . $description;
                }
                $this->suppressionService->createSuppression(
                    $sendAttempt->getSend()->getProject(),
                    $sendRecipient->getAddress(),
                    SuppressionReason::BOUNCE,
                    $description
                );

                $attemptRecipient->setIsSuppressed(true);
                $this->em->persist($attemptRecipient);
                $this->em->flush();
            }
            if ($attemptRecipient->getBouncedReason() === BounceReason::INFRASTRUCTURE) {
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
