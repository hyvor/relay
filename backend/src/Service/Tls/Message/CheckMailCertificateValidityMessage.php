<?php

declare(strict_types=1);

namespace App\Service\Tls\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
readonly class CheckMailCertificateValidityMessage
{
}
