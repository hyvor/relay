<?php

namespace App\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Service\Ip\IpSelector;
use App\Service\Queue\QueueService;
use App\Service\Send\Message\RouteNullIpsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteNullIpsMessageHandler
{

    public function __construct(
        private IpSelector $ipSelector,
        private QueueService $queueService,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(RouteNullIpsMessage $message): void
    {
        $queue = $this->queueService->getQueueById($message->queueId);

        if ($queue === null) {
            return;
        }

        $sends = $this->em->getRepository(Send::class)->findBy([
            'queue' => $queue,
            'ip_address' => null,
            'queued' => true,
        ]);

        foreach ($sends as $send) {
            $recipientCount = $send->getRecipients()->filter(
                fn(SendRecipient $r) => $r->getStatus() === SendRecipientStatus::QUEUED
            )->count();

            if ($recipientCount === 0) {
                continue;
            }

            $ip = $this->ipSelector->selectForQueue($queue, $recipientCount);
            if ($ip !== null) {
                $send->setIpAddress($ip);
            }
        }

        $this->em->flush();
    }

}
