<?php

namespace App\Api\Console\Object;

use App\Entity\SendAttemptRecipient;
use App\Entity\Type\SendAttemptRecipientStatus;

class SendAttemptRecipientObject
{

    public int $id;
    public int $created_at;
    public int $recipient_id;
    public SendAttemptRecipientStatus $status;
    public int $smtp_code;
    public ?string $smtp_enhanced_code = null;
    public string $smtp_message;
    public bool $is_suppressed;

    public function __construct(SendAttemptRecipient $recipient)
    {
        $this->id = $recipient->getId();
        $this->created_at = $recipient->getCreatedAt()->getTimestamp();
        $this->recipient_id = $recipient->getSendRecipientId();
        $this->status = $recipient->getStatus();
        $this->smtp_code = $recipient->getSmtpCode();
        $this->smtp_enhanced_code = $recipient->getSmtpEnhancedCode();
        $this->smtp_message = $recipient->getSmtpMessage();
        $this->is_suppressed = $recipient->getIsSuppressed();
    }

}