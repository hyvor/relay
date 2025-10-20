<?php

namespace App\Service\Smtp;

use App\Entity\SendAttemptRecipient;

/**
 * Most of this is based on https://smtpfieldmanual.com/
 *
 * Reviewed and supported providers:
 * - Apple
 */
class SmtpResponseParser
{

    public function __construct(
        private int $code,
        private ?string $enhancedCode,
        private string $message,
    ) {
    }

    public function isBounce(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    /**
     * This checks if the SMTP response indicates a recipient bounce.
     * ex: the bounce was due to an issue with the recipient address.
     * This is important for suppressions. We only want to suppress on recipient bounces.
     */
    public function isRecipientBounce(): bool
    {
        // must be a bounce first
        if (!$this->isBounce()) {
            return false;
        }

        /**
         * Most modern SMTP servers provide an enhanced status code for bounces.
         * If it is not present, it is likely an older server.
         * In that case, we assume there is no need for suppression.
         * (Note: this is an assumption that we may want to revisit in the future.)
         */
        if ($this->enhancedCode === null) {
            return false;
        }

        // Enhanced status codes starting with 5.1.x indicate recipient issues
        // Google: 550 5.1.1 The email account that you tried to reach does not exist.
        // Apple: 550 5.1.1 <example@icloud.com>: user does not exist

        return true;
    }

    public static function fromAttemptRecipient(SendAttemptRecipient $recipient): self
    {
        return new self(
            $recipient->getSmtpCode(),
            $recipient->getSmtpEnhancedCode(),
            $recipient->getSmtpMessage()
        );
    }

}