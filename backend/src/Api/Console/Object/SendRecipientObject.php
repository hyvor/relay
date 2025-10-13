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
    public int $try_count;

    public function __construct(SendRecipient $recipient)
    {
        $this->id = $recipient->getId();
        $this->type = $recipient->getType();
        $this->address = $recipient->getAddress();
        $this->name = $recipient->getName();
        $this->status = $recipient->getStatus();
        $this->try_count = $recipient->getTryCount();
    }

}
