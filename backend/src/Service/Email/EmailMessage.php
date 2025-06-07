<?php

namespace App\Service\Email;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('email')]
class EmailMessage
{

    public function __construct(public string $email)
    {
    }

}