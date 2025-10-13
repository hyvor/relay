<?php

namespace App\Service\Send\Event;

use App\Entity\SendRecipient;
use App\Entity\Suppression;

readonly class SendRecipientSuppressedEvent
{
    public function __construct(
        private SendRecipient $sendRecipient,
        private Suppression $suppression,
    ) {
    }

    public function getSendRecipient(): SendRecipient
    {
        return $this->sendRecipient;
    }

    public function getSuppression(): Suppression
    {
        return $this->suppression;
    }


}
