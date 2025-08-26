<?php

namespace App\Api\Console\Object;

use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;

class SendRecipientObject
{

    public int $id;
    public SendRecipientType $type;
    public string $address;
    public string $name;
    public SendRecipientStatus $status;
    public ?int $accepted_at = null;
    public ?int $bounced_at = null;
    public ?int $failed_at = null;

    public function __construct(SendRecipient $recipient)
    {
        $this->id = $recipient->getId();
        $this->type = $recipient->getType();
        $this->address = $recipient->getAddress();
        $this->name = $recipient->getName();
        $this->status = $recipient->getStatus();
        $this->accepted_at = $recipient->getAcceptedAt()?->getTimestamp();
        $this->bounced_at = $recipient->getBouncedAt()?->getTimestamp();
        $this->failed_at = $recipient->getFailedAt()?->getTimestamp();
    }

}