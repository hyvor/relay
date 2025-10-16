<?php

namespace App\Api\Console\Object;

use App\Entity\SendAttemptRecipient;
use App\Entity\Type\SendRecipientStatus;

class SendAttemptRecipientObject
{

    public int $id;
    public int $created_at;
    public SendRecipientStatus $recipient_status;
    public int $smtp_code;
    public ?string $smtp_enhanced_code = null;
    public string $smtp_message;

    public function __construct(SendAttemptRecipient $recipient)
    {
        $this->id = $recipient->getId();
        $this->created_at = $recipient->getCreatedAt()->getTimestamp();
        $this->recipient_status = $recipient->getRecipientStatus();
        $this->smtp_code = $recipient->getSmtpCode();
        $this->smtp_enhanced_code = $recipient->getSmtpEnhancedCode();
        $this->smtp_message = $recipient->getSmtpMessage();
    }

}