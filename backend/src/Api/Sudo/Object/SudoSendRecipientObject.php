<?php

namespace App\Api\Sudo\Object;

use App\Entity\SendRecipient;

class SudoSendRecipientObject
{
    public int $id;
    public string $address;
    public string $status;

    public function __construct(SendRecipient $recipient)
    {
        $this->id = $recipient->getId();
        $this->address = $recipient->getAddress();
        $this->status = $recipient->getStatus()->value;
    }
}
