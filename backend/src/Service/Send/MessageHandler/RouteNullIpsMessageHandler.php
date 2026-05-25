<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Ip\IpAddressService;
use App\Service\Queue\QueueService;
use App\Service\Send\Message\RouteNullIpsMessage;
use App\Service\Send\SendService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteNullIpsMessageHandler
{

    public function __construct(
        private SendService $sendService,
        private IpAddressService $ipAddressService,
        private QueueService $queueService,
    ) {
    }

    public function __invoke(RouteNullIpsMessage $message): void
    {
        $queue = $this->queueService->getQueueById($message->queueId);

        if ($queue === null) {
            return;
        }

        $newIp = $this->ipAddressService->getRandomIpForQueue($queue);

        $this->sendService->updateNullIpSendsForQueue(
            $queue->getId(),
            $newIp?->getId()
        );
    }

}
