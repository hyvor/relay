<?php

namespace App\Service\Email\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('email')]
readonly class EmailSendMessage
{

    public function __construct(
        public int $sendId,
        public string $rawEmail
    )
    {
    }

}