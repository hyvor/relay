<?php

namespace App\Service\IncomingMail;

use App\Api\Local\Input\ArfInput;
use App\Api\Local\Input\DsnInput;
use App\Entity\DebugIncomingEmail;
use App\Entity\Send;
use App\Entity\SendRecipient;
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
    ) {}

    public function handleIncomingBounce(
        string $bounceUuid,
        DsnInput $dsnInput,
        DebugIncomingEmail $debugIncomingEmail,
    ): void {
        // we are not interested in delayed or delivered actions
        // most email clients do not even send delivered reports
        if ($dsnInput->Action !== 'failed') {
            $this->logger->info('Received bounce with non-failed action', [
                'uuid' => $bounceUuid,
                'recipient' => $dsnInput->EmailAddress,
                'action' => $dsnInput->Action,
            ]);
            return;
        }

        $bounceReason = $dsnInput->BounceReason;
        if ($bounceReason === null) {
            $this->logger->info('Received failed DSN that is not a bounce', [
                'uuid' => $bounceUuid,
                'recipient' => $dsnInput->EmailAddress,
                'status' => $dsnInput->Status,
            ]);
            return;
        }

        if ($bounceReason === BounceReason::UNKNOWN) {
            $this->logger->info('Received bounce that is not a recipient bounce or infrastructure error', [
                'uuid' => $bounceUuid,
                'recipient' => $dsnInput->EmailAddress,
                'status' => $dsnInput->Status,
            ]);
        }

        // Some providers (Microsoft, Yahoo) redact the recipient address.
        // Then, the X-Relay-Recipient header of the original message identifies the recipient.
        if ($dsnInput->EmailAddress === '' && $dsnInput->RecipientUuid === '') {
            $this->logger->info('Received bounce without a recipient', [
                'uuid' => $bounceUuid,
            ]);
            return;
        }

        $send = $this->sendService->getSendByUuid($bounceUuid);

        if ($send === null) {
            $this->logger->info('Received bounce with unknown send UUID', [
                'uuid' => $bounceUuid,
                'recipient' => $dsnInput->EmailAddress,
            ]);
            return;
        }

        $sendRecipient = $this->resolveSendRecipient($send, $dsnInput->RecipientUuid, $dsnInput->EmailAddress);
        if ($sendRecipient === null) {
            $this->logger->info('Received bounce with unknown recipient', [
                'uuid' => $bounceUuid,
                'recipient' => $dsnInput->EmailAddress,
                'recipient_uuid' => $dsnInput->RecipientUuid,
            ]);
            return;
        }

        $this->sendRecipientService->updateSendRecipientStatus($sendRecipient, SendRecipientStatus::BOUNCED, $bounceReason);

        $this->sendFeedbackService->createSendFeedback(
            SendFeedbackType::BOUNCE,
            $sendRecipient,
            $debugIncomingEmail,
            bounceReason: $bounceReason,
        );

        if ($bounceReason === BounceReason::RECIPIENT) {
            $this->suppressionService->createSuppression(
                $send->getProject(),
                $sendRecipient->getAddress(),
                SuppressionReason::BOUNCE,
                $dsnInput->ReadableText
            );
        } elseif ($bounceReason === BounceReason::INFRASTRUCTURE) {
            $this->logger->info('Received infrastructure error', [
                'uuid' => $bounceUuid,
                'recipient' => $sendRecipient->getAddress(),
            ]);

            $this->infrastructureBounceService->createInfrastructureBounce(
                $sendRecipient->getId(),
                0, // SMTP code is not available in DSN, using 0 as default
                $dsnInput->Status,
                $dsnInput->ReadableText
            );
        }

        $bounceObject = new BounceDto($dsnInput->ReadableText, $dsnInput->Status);
        $this->ed->dispatch(new IncomingBounceEvent($send, $sendRecipient, $bounceObject));
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

        if ($arfInput->OriginalRcptTo === '' && $arfInput->RecipientUuid === '') {
            $this->logger->info('Received complaint without a recipient', [
                'uuid' => $uuid,
            ]);
            return;
        }

        $sendRecipient = $this->resolveSendRecipient($send, $arfInput->RecipientUuid, $arfInput->OriginalRcptTo);
        if ($sendRecipient === null) {
            // @codeCoverageIgnoreStart
            $this->logger->error('Failed to get send recipient', [
                'uuid' => $uuid,
                'recipient' => $arfInput->OriginalRcptTo,
                'recipient_uuid' => $arfInput->RecipientUuid,
            ]);
            return;
            // @codeCoverageIgnoreEnd
        }

        if ($this->sendFeedbackService->hasComplaintForRecipient($sendRecipient)) {
            $this->logger->info('Received duplicate complaint', [
                'uuid' => $uuid,
                'recipient' => $sendRecipient->getAddress(),
            ]);
            return;
        }

        $this->sendFeedbackService->createSendFeedback(
            SendFeedbackType::COMPLAINT,
            $sendRecipient,
            $debugIncomingEmail,
            $arfInput->FeedbackType
        );

        $this->sendRecipientService->updateSendRecipientStatus($sendRecipient, SendRecipientStatus::COMPLAINED);

        $this->suppressionService->createSuppression(
            $send->getProject(),
            $sendRecipient->getAddress(),
            SuppressionReason::COMPLAINT,
            $arfInput->ReadableText
        );

        $complaintObject = new ComplaintDto($arfInput->ReadableText, $arfInput->FeedbackType);
        $this->ed->dispatch(new IncomingComplaintEvent($send, $sendRecipient, $complaintObject));
    }

    /**
     * try X-Relay-Recipient first then fall back to the email address in the report.
     */
    private function resolveSendRecipient(Send $send, string $recipientUuid, string $email): ?SendRecipient
    {
        if ($recipientUuid !== '') {
            $sendRecipient = $this->sendRecipientService->getSendRecipientByUuid($recipientUuid);

            if ($sendRecipient !== null && $sendRecipient->getSend()->getId() === $send->getId()) {
                return $sendRecipient;
            }
        }

        if ($email === '') {
            return null;
        }

        return $this->sendRecipientService->getSendRecipientByEmail($send, $email);
    }
}
