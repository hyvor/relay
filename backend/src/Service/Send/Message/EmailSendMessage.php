<?php

namespace App\Service\Send\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('email')]
readonly class EmailSendMessage
{

    public function __construct(
        public int $sendId,
        public string $from,
        public string $to,
        public string $rawEmail,
    )
    {
    }

}