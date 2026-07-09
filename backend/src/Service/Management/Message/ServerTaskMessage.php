<?php

declare(strict_types=1);

namespace App\Service\Management\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('scheduler_server')]
readonly class ServerTaskMessage
{
}
