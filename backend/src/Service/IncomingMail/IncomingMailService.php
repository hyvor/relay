<?php

namespace App\Service\IncomingMail;

use App\Api\Console\Object\BounceObject;
use App\Api\Console\Object\ComplaintObject;
use App\Api\Local\Input\ArfInput;
use App\Api\Local\Input\DsnInput;
use App\Entity\DebugIncomingEmail;
use App\Entity\Type\SendFeedbackType;
use App\Entity\Type\SuppressionReason;
use App\Service\IncomingMail\Event\IncomingBounceEvent;
use App\Service\IncomingMail\Event\IncomingComplaintEvent;
use App\Service\Send\SendService;
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
        private SendFeedbackService $sendFeedbackService,
        private LoggerInterface $logger,
        private EventDispatcherInterface $ed
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

            // We are only interested in bounces that have a status code that starts with 5
            // (permanent failures)
            if ($recipient->Status[0] !== '5') {
                $this->logger->info('Received bounce with non-permanent status code', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                    'status' => $recipient->Status,
                ]);
                return;
            }

            $send = $this->sendService->getSendByUuid($bounceUuid);

            if ($send === null) {
                $this->logger->error('Failed to get send by UUID', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                ]);
                return;
            }

            $sendRecipient = $this->sendRecipientService->getSendRecipientByEmail($send, $recipient->EmailAddress);
            if ($sendRecipient === null) {
                $this->logger->error('Failed to get send recipient by email', [
                    'uuid' => $bounceUuid,
                    'recipient' => $recipient->EmailAddress,
                ]);
                return;
            }

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

            $bounceObject = new BounceObject($dsnInput->ReadableText, $recipient->Status);
            $this->ed->dispatch(new IncomingBounceEvent($send, $sendRecipient, $bounceObject));
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

        $sendRecipient = $this->sendRecipientService->getSendRecipientByEmail($send, $arfInput->OriginalRcptTo);
        if ($sendRecipient === null) {
            $this->logger->error('Failed to get send recipient by email', [
                'uuid' => $uuid,
                'recipient' => $arfInput->OriginalRcptTo,
            ]);
            return;
        }

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

        $complaintObject = new ComplaintObject($arfInput->ReadableText, $arfInput->FeedbackType);
        $this->ed->dispatch(new IncomingComplaintEvent($send, $sendRecipient, $complaintObject));
    }
}
