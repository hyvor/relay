<?php

namespace App\Service\Management\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('default')]
readonly class HealthMessage
{
}