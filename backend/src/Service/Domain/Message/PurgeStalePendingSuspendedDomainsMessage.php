<?php

namespace App\Service\Domain\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage()]
readonly class PurgeStalePendingSuspendedDomainsMessage
{
}