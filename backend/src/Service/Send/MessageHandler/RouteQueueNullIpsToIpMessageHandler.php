<?php

namespace App\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\WarmupStatus;
use App\Service\Ip\IpAddressService;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteQueueNullIpsToIpMessageHandler
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private EntityManagerInterface $em,
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

            if (!$ipAddress->isWarmingUp()) {
                $send->setIpAddress($ipAddress);
                continue;
            }

            $conn = $this->em->getConnection();
            $rows = $conn->executeStatement(
                'UPDATE ip_addresses SET warmup_sent_today = warmup_sent_today + :count WHERE id = :id AND warmup_status = :status AND warmup_sent_today + :count <= warmup_max_today',
                [
                    'count' => $recipientCount,
                    'id' => $ipAddress->getId(),
                    'status' => WarmupStatus::WARMING->value,
                ]
            );

            if ($rows > 0) {
                $send->setIpAddress($ipAddress);
            }
        }

        $this->em->flush();
    }

}
