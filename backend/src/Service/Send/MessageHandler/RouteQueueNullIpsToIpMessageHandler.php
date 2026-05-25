<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Ip\IpAddressService;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use App\Service\Send\SendService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteQueueNullIpsToIpMessageHandler
{

    public function __construct(
        private SendService $sendService,
        private IpAddressService $ipAddressService,
    ) {
    }

    public function __invoke(RouteQueueNullIpsToIpMessage $message): void
    {
        $ipAddress = $this->ipAddressService->getIpAddressById($message->ipAddressId);

        if ($ipAddress === null) {
            return;
        }

        $queue = $ipAddress->getQueue();

        if ($queue === null || $queue->getId() !== $message->queueId) {
            return;
        }

        $this->sendService->updateNullIpSendsForQueue(
            $message->queueId,
            $message->ipAddressId
        );
    }

}
