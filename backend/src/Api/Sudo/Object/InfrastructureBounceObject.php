<?php

namespace App\Api\Sudo\Object;

use App\Entity\InfrastructureBounce;

class InfrastructureBounceObject
{
    public int $id;
    public int $created_at;
    public int $updated_at;
    public bool $is_read;
    public int $smtp_code;
    public string $smtp_enhanced_code;
    public string $smtp_message;
    public int $send_recipient_id;

    public function __construct(InfrastructureBounce $bounce)
    {
        $this->id = $bounce->getId();
        $this->created_at = $bounce->getCreatedAt()->getTimestamp();
        $this->updated_at = $bounce->getUpdatedAt()->getTimestamp();
        $this->is_read = $bounce->isRead();
        $this->smtp_code = $bounce->getSmtpCode();
        $this->smtp_enhanced_code = $bounce->getSmtpEnhancedCode();
        $this->smtp_message = $bounce->getSmtpMessage();
        $this->send_recipient_id = $bounce->getSendRecipientId();
    }
}

