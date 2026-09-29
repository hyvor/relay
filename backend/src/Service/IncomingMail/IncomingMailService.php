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
use App\Service\SendAttempt\SendAttemptService;
use App\Service\SendFeedback\SendFeedbackService;
use App\Service\SendRecipient\SendRecipientService;
use App\Service\Suppression\SuppressionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IncomingMailService
{
    public function __construct(
        private SendService $sendService,
        private SuppressionService $suppressionService,
        private SendRecipientService $sendRecipientService,
        private SendAttemptService $sendAttemptService,
        private SendFeedbackService $sendFeedbackService,
        private InfrastructureBounceService $infrastructureBounceService,
        private LoggerInterface $logger,
        private EventDispatcherInterface $ed,
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

            $bounceReason = $recipient->BounceReason;
            if ($bounceReason === null) {
                $this->logger->info('Received failed DSN that is not a bounce', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                    'status' => $recipient->Status,
                ]);
                return;
            }

            if ($bounceReason === BounceReason::UNKNOWN) {
                $this->logger->info('Received bounce that is not a recipient bounce or infrastructure error', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                    'status' => $recipient->Status,
                ]);
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

            $this->sendRecipientService->updateSendRecipientStatus($sendRecipient, SendRecipientStatus::BOUNCED, $bounceReason);

            $this->sendFeedbackService->createSendFeedback(
                SendFeedbackType::BOUNCE,
                $send,
                $sendRecipient,
                $debugIncomingEmail,
                $this->sendAttemptService->getAcceptedSendAttemptOfRecipient($sendRecipient)?->getIpAddress(),
            );

            if ($bounceReason === BounceReason::RECIPIENT) {
                $this->suppressionService->createSuppression(
                    $send->getProject(),
                    $recipient->EmailAddress,
                    SuppressionReason::BOUNCE,
                    $dsnInput->ReadableText
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

        if ($arfInput->OriginalRcptTo === '') {
            // Redacted reports do not identify the recipient, so every redacted complaint
            // on a send is treated as a duplicate of the first one
            if ($this->sendFeedbackService->hasComplaint($send, null)) {
                $this->logger->info('Received duplicate redacted complaint', [
                    'uuid' => $uuid,
                ]);
                return;
            }

            $this->sendFeedbackService->createSendFeedback(
                SendFeedbackType::COMPLAINT,
                $send,
                null,
                $debugIncomingEmail,
                $send->getIpAddress(),
                $arfInput->FeedbackType
            );
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

        if ($this->sendFeedbackService->hasComplaint($send, $sendRecipient)) {
            $this->logger->info('Received duplicate complaint', [
                'uuid' => $uuid,
                'recipient' => $arfInput->OriginalRcptTo,
            ]);
            return;
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
            $send,
            $sendRecipient,
            $debugIncomingEmail,
            $this->sendAttemptService->getAcceptedSendAttemptOfRecipient($sendRecipient)?->getIpAddress(),
            $arfInput->FeedbackType
        );

        $complaintObject = new ComplaintDto($arfInput->ReadableText, $arfInput->FeedbackType);
        $this->ed->dispatch(new IncomingComplaintEvent($send, $sendRecipient, $complaintObject));
    }
}
