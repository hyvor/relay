<?php

namespace App\Service\Smtp;

use App\Entity\SendAttemptRecipient;

/**
 * Most of this is based on https://smtpfieldmanual.com/
 * See /hosting/providers
 */
class SmtpResponseParser
{

    const RECIPIENT_ENHANCED_CODES = [
        '5.1.1', // Bad destination mailbox address
        '5.1.2', // Bad destination system address
        '5.1.3', // Bad destination mailbox address syntax
        '5.5.0' // Other or undefined mailbox status
    ];

    public function __construct(
        private ?int $code,
        private ?string $enhancedCode,
        private string $message,
    ) {
    }

    public function isBounce(): bool
    {
        if ($this->code !== null) {
            return $this->code >= 500 && $this->code < 600;
        } elseif ($this->enhancedCode !== null) {
            return str_starts_with($this->enhancedCode, '5.');
        }
        return false;
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
         * In that case, we assume there is no need for suppressions.
         * Not developed enough to support enhanced codes, not developed enough to ban based on repeated bounces.
         * (Note: this is an assumption that we may want to revisit in the future.)
         */
        if ($this->enhancedCode === null) {
            return false;
        }

        return in_array($this->enhancedCode, self::RECIPIENT_ENHANCED_CODES, true);
    }

    /**
     * Checks if the error is due to infrastructure issues (e.g., spam filtering, policy restrictions).
     * These must be recorded
     */
    public function isInfrastructureError(): bool
    {
        // must have an enhanced code
        if ($this->enhancedCode === null) {
            return false;
        }

        return str_starts_with($this->enhancedCode, '5.7.') || str_starts_with($this->enhancedCode, '4.7.');
    }

    public function getFullMessage(): string
    {
        $code = $this->code ?? '';
        $enhancedCode = $this->enhancedCode !== null ? " $this->enhancedCode" : '';
        $message = $this->message !== '' ? ' ' . substr($this->message, 0, 255) : '';
        return "$code$enhancedCode$message";
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