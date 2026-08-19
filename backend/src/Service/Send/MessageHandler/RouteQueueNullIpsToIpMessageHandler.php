<?php

namespace App\Service\Send\MessageHandler;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\WarmupStatus;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\WarmupScheduleService;
use App\Service\Send\Message\RouteQueueNullIpsToIpMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RouteQueueNullIpsToIpMessageHandler
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private WarmupScheduleService $warmupScheduleService,
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

        $warmup = $this->warmupScheduleService->getCurrentWarmupSchedule($ipAddress);

        $sends = $this->em->getRepository(Send::class)->findBy([
            'queue' => $queue,
            'ip_address' => null,
            'queued' => true,
        ]);

        foreach ($sends as $send) {
            $recipientCount = $this->em->getRepository(SendRecipient::class)->count([
                'send' => $send,
                'status' => SendRecipientStatus::QUEUED,
            ]);

            if ($recipientCount === 0) {
                continue;
            }

            if ($warmup === null || $warmup->getStatus() !== WarmupStatus::WARMING) {
                $send->setIpAddress($ipAddress);
                continue;
            }

            if ($warmup->getSentToday() + $recipientCount <= $warmup->getMaxToday()) {
                $conn = $this->em->getConnection();
                $conn->executeStatement(
                    'UPDATE warmup_schedules SET sent_today = sent_today + :count WHERE id = :id',
                    [
                        'count' => $recipientCount,
                        'id' => $warmup->getId(),
                    ]
                );
                $send->setIpAddress($ipAddress);
            }
        }

        $this->em->flush();
    }

}
