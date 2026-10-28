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

    private const int BATCH_SIZE = 100;

    public function __construct(
        private IpSelector $ipSelector,
        private QueueService $queueService,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(RouteNullIpsMessage $message): void
    {
        $lastId = 0;

        do {
            $queue = $this->queueService->getQueueById($message->queueId);

            if ($queue === null) {
                return;
            }

            /** @var Send[] $sends */
            $sends = $this->em
                ->createQueryBuilder()
                ->select('s')
                ->from(Send::class, 's')
                ->where('s.queue = :queue')
                ->andWhere('s.ip_address IS NULL')
                ->andWhere('s.queued = true')
                ->andWhere('s.id > :lastId')
                ->orderBy('s.id', 'ASC')
                ->setMaxResults(self::BATCH_SIZE)
                ->setParameter('queue', $queue)
                ->setParameter('lastId', $lastId)
                ->getQuery()
                ->getResult();

            foreach ($sends as $send) {
                $lastId = $send->getId();

                $recipientCount = $this->em->getRepository(SendRecipient::class)->count([
                    'send' => $send,
                    'status' => SendRecipientStatus::QUEUED,
                ]);

                if ($recipientCount === 0) {
                    continue;
                }

                $ip = $this->ipSelector->selectForQueue($queue, $recipientCount);
                if ($ip !== null) {
                    $send->setIpAddress($ip);
                }
            }

            $this->em->flush();
            $this->em->clear();
        } while (count($sends) === self::BATCH_SIZE);
    }

}
