<?php

namespace App\Service\IncomingMail;

use App\Api\Local\Input\ArfInput;
use App\Api\Local\Input\DsnInput;
use App\Entity\DebugIncomingEmail;
use App\Entity\Type\BounceReason;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SuppressionReason;
use App\Service\IncomingMail\Dto\BounceDto;
use App\Service\IncomingMail\Dto\ComplaintDto;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\InfrastructureBounce\InfrastructureBounceService;
use App\Service\Send\SendService;
use App\Service\SendFeedback\SendFeedbackService;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Suppression\SuppressionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IncomingMailService
{
    public function __construct(
        private SendService $sendService,
        private SuppressionService $suppressionService,
        private SendRecipientService $sendRecipientService,
        private SendFeedbackService $sendFeedbackService,
        private InfrastructureBounceService $infrastructureBounceService,
        private LoggerInterface $logger,
        private EventDispatcherInterface $ed,
        private EntityManagerInterface $em,
    ) {
    }

    public function handleIncomingBounce(
        string $bounceUuid,
        DsnInput $dsnInput,
        DebugIncomingEmail $debugIncomingEmail,
    ): void {
        $recipients = $dsnInput->Recipients;

        if (count($recipients) === 0) {
            $this->logger->error('Received bounce with no recipients', [
                'uuid' => $bounceUuid
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            // we are not interested in delayed or delivered actions
            // most email clients do not even send delivered reports
            if ($recipient->Action !== 'failed') {
                $this->logger->info('Received bounce with non-failed action', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                    'action' => $recipient->Action,
                ]);
                return;
            }

            $bounceReason = $this->classifyBounceReason($recipient->Status);
            if ($bounceReason === null) {
                $this->logger->info('Received bounce that is not a recipient bounce or infrastructure error', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                    'status' => $recipient->Status,
                ]);
                return;
            }

            $send = $this->sendService->getSendByUuid($bounceUuid);

            if ($send === null) {
                $this->logger->info('Received bounce with unknown send UUID', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                ]);
                return;
            }

            $sendRecipient = $this->sendRecipientService->getSendRecipientByEmail($send, $recipient->EmailAddress);
            if ($sendRecipient === null) {
                $this->logger->info('Received bounce with unknown recipient', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                ]);
                return;
            }

            $this->sendRecipientService->updateSendRecipientStatus($sendRecipient, SendRecipientStatus::BOUNCED);
            $sendRecipient->setBouncedReason($bounceReason);
            $this->em->persist($sendRecipient);
            $this->em->flush();

            if ($bounceReason === BounceReason::RECIPIENT) {
                $this->suppressionService->createSuppression(
                    $send->getProject(),
                    $recipient->EmailAddress,
                    SuppressionReason::BOUNCE,
                    $dsnInput->ReadableText
                );

                $this->sendFeedbackService->createSendFeedback(
                    SendFeedbackType::BOUNCE,
                    $sendRecipient,
                    $debugIncomingEmail
                );

                $bounceObject = new BounceDto($dsnInput->ReadableText, $recipient->Status);
                $this->ed->dispatch(new IncomingBounceEvent($send, $sendRecipient, $bounceObject));
            } elseif ($bounceReason === BounceReason::INFRASTRUCTURE) {
                $this->logger->info('Received infrastructure error', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                ]);

                $this->infrastructureBounceService->createInfrastructureBounce(
                    $sendRecipient->getId(),
                    0, // SMTP code is not available in DSN, using 0 as default
                    $recipient->Status,
                    $dsnInput->ReadableText
                );
            }
        }
    }

    private function classifyBounceReason(?string $status): ?BounceReason
    {
        if ($status === null) {
            return null;
        }

        // Recipient bounce codes
        if (in_array($status, ['5.1.1', '5.1.2', '5.1.3', '5.5.0'], true)) {
            return BounceReason::RECIPIENT;
        }

        // Infrastructure bounce codes
        if (str_starts_with($status, '5.7.') || str_starts_with($status, '4.7.')) {
            return BounceReason::INFRASTRUCTURE;
        }

        return null;
    }

    public function handleIncomingComplaint(
        ArfInput $arfInput,
        DebugIncomingEmail $debugIncomingEmail,
    ): void {
        $parts = explode('@', $arfInput->MessageId);

        if (count($parts) < 2) {
            $this->logger->error('Received complaint with invalid Message-ID', [
                'message-id' => $arfInput->MessageId
            ]);
            return;
        }

        $uuid = $parts[0];
        $send = $this->sendService->getSendByUuid($uuid);

        if ($send === null) {
            $this->logger->error('Failed to get send by UUID', [
                'uuid' => $uuid
            ]);
            return;
        }

        $sendRecipient = $this->sendRecipientService->getSendRecipientByEmail($send, $arfInput->OriginalRcptTo);
        if ($sendRecipient === null) {
            // @codeCoverageIgnoreStart
            $this->logger->error('Failed to get send recipient by email', [
                'uuid' => $uuid,
                'recipient' => $arfInput->OriginalRcptTo,
            ]);
            return;
            // @codeCoverageIgnoreEnd
        }

        $this->sendRecipientService->updateSendRecipientStatus($sendRecipient, SendRecipientStatus::COMPLAINED);

        $this->suppressionService->createSuppression(
            $send->getProject(),
            $arfInput->OriginalRcptTo,
            SuppressionReason::COMPLAINT,
            $arfInput->ReadableText
        );

        $this->sendFeedbackService->createSendFeedback(
            SendFeedbackType::COMPLAINT,
            $sendRecipient,
            $debugIncomingEmail
        );

        $complaintObject = new ComplaintDto($arfInput->ReadableText, $arfInput->FeedbackType);
        $this->ed->dispatch(new IncomingComplaintEvent($send, $sendRecipient, $complaintObject));
    }
}
