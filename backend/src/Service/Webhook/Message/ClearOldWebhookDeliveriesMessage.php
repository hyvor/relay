<?php

namespace App\Service\Webhook\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(MessageTransport::ASYNC)]
class ClearOldWebhookDeliveriesMessage
{

}
