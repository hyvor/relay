<?php

namespace App\Service\Send\MessageHandler;

use App\Repository\IpAddressRepository;
use App\Repository\SendRepository;
use App\Service\Queue\QueueService;
use App\Service\Send\Message\RouteNullIpsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteNullIpsMessageHandler
{

    public function __construct(
        private SendRepository $sendRepository,
        private IpAddressRepository $ipAddressRepository,
        private QueueService $queueService,
    ) {
    }

    public function __invoke(RouteNullIpsMessage $message): void
    {
        $queue = $this->queueService->getQueueById($message->queueId);

        if ($queue === null) {
            return;
        }

        $newIp = $this->ipAddressRepository->getRandomIpForQueue($queue);

        $this->sendRepository->updateNullIpSendsForQueue(
            $queue->getId(),
            $newIp?->getId()
        );
    }

}
