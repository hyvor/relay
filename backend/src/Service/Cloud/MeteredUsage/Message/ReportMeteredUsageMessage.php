<?php

namespace App\Service\Cloud\MeteredUsage\Message;

use App\Service\App\MessageTransport;
use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Reports the metered usage of all organizations for every day
 * since the last reported day, up to yesterday
 */
#[AsMessage(MessageTransport::CLOUD)]
readonly class ReportMeteredUsageMessage
{
}
