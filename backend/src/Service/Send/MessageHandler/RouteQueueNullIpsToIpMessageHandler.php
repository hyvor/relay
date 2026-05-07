<?php

namespace App\Service\Send\MessageHandler;

use App\Repository\IpAddressRepository;
use App\Repository\SendRepository;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteQueueNullIpsToIpMessageHandler
{

    public function __construct(
        private SendRepository $sendRepository,
        private IpAddressRepository $ipAddressRepository,
    ) {
    }

    public function __invoke(RouteQueueNullIpsToIpMessage $message): void
    {
        $ipAddress = $this->ipAddressRepository->find($message->ipAddressId);

        if ($ipAddress === null) {
            return;
        }

        $queue = $ipAddress->getQueue();

        if ($queue === null || $queue->getId() !== $message->queueId) {
            return;
        }

        $this->sendRepository->updateNullIpSendsForQueue(
            $message->queueId,
            $message->ipAddressId
        );
    }

}
