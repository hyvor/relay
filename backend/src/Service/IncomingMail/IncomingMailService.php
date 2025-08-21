<?php

namespace App\Service\IncomingMail;

use App\Api\Local\Input\ArfInput;
use App\Api\Local\Input\DsnInput;
use App\Entity\Type\SuppressionReason;
use App\Service\Send\SendService;
use App\Service\Suppression\SuppressionService;
use Psr\Log\LoggerInterface;

class IncomingMailService
{
    public function __construct(
        private SendService $sendService,
        private SuppressionService $suppressionService,
        private LoggerInterface $logger,
    ) {
    }

    public function handleIncomingBounce(
        string $bounceUuid,
        DsnInput $dsnInput
    ): void
    {

        $recipients = $dsnInput->Recipients;

        if (count($recipients) === 0) {
            $this->logger->error('Received bounce with no recipients', [
                'UUID' => $bounceUuid
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            // we are not interested in delayed or delivered actions
            // most email clients do not even send delivered reports
            if ($recipient->Action !== 'failed') {
                $this->logger->info('Received bounce with non-failed action', [
                    'UUID' => $bounceUuid,
                    'Recipient' => $recipient->EmailAddress,
                    'Action' => $recipient->Action,
                ]);
                return;
            }

            // We are only interested in bounces that have a status code that starts with 5
            // (permanent failures)
            if ($recipient->Status[0] !== '5') {
                $this->logger->info('Received bounce with non-permanent status code', [
                    'UUID' => $bounceUuid,
                    'Recipient' => $recipient->EmailAddress,
                    'Status' => $recipient->Status,
                ]);
                return;
            }

            $send = $this->sendService->getSendByUuid($bounceUuid);

            if ($send === null) {
                $this->logger->error('Failed to get send by UUID', [
                    'UUID' => $bounceUuid,
                    'Recipient' => $recipient->EmailAddress,
                ]);
                return;
            }

            $this->suppressionService->createSuppression(
                $send->getProject(),
                $recipient->EmailAddress,
                SuppressionReason::BOUNCE,
                $dsnInput->ReadableText
            );
        }
    }

    public function handleIncomingFbl(ArfInput $arfInput): void
    {
        $parts = explode('@', $arfInput->MessageId);

        if (count($parts) < 2) {
            $this->logger->error('Received FBL with invalid Message-ID', [
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

        $this->suppressionService->createSuppression(
            $send->getProject(),
            $arfInput->OriginalMailFrom,    // This is wrong. How we can get all users?
            SuppressionReason::FBL,
            $arfInput->ReadableText
        );
    }
}
