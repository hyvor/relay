<?php

namespace App\Api\Console\Object;

use App\Entity\SendFeedback;
use App\Entity\Type\SendFeedbackType;

class SendFeedbackObject
{

    public int $id;
    public int $created_at;
    public SendFeedbackType $type;
    public int $recipient_id;
    public int $debug_incoming_email_id;

    public function __construct(SendFeedback $sendFeedback)
    {
        $this->id = $sendFeedback->getId();
        $this->created_at = $sendFeedback->getCreatedAt()->getTimestamp();
        $this->type = $sendFeedback->getType();
        $this->recipient_id = $sendFeedback->getSendRecipient()->getId();
        $this->debug_incoming_email_id = $sendFeedback->getDebugIncomingEmail()->getId();
    }

}
