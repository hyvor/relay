<?php

namespace App\Service\Send;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use App\Repository\SendRepository;
use App\Service\Send\Dto\SendingAttachment;
use App\Service\Send\Exception\EmailTooLargeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Mime\Address;

class SendService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EmailBuilder $emailBuilder,
        private SendRepository $sendRepository,
        private RecipientFactory $recipientFactory,
    ) {
    }

    /**
     * @return ArrayCollection<int, Send>
     */
    public function getSends(
        Project $project,
        ?SendRecipientStatus $status,
        ?string $fromSearch,
        ?string $toSearch,
        ?string $subjectSearch,
        int $limit,
        int $offset
    ): ArrayCollection {
        $qb = $this->sendRepository->createQueryBuilder('s');

        $qb
            ->distinct()
            ->where('s.project = :project')
            ->setParameter('project', $project)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('s.created_at', 'DESC');

        if ($status !== null) {
            $qb->join('s.recipients', 'r')
                ->andWhere('r.status = :status')
                ->setParameter('status', $status->value);
        }

        if ($fromSearch !== null) {
            $qb->andWhere('s.from_address LIKE :fromSearch')
                ->setParameter('fromSearch', '%' . $fromSearch . '%');
        }

        if ($toSearch !== null) {
            $qb->join('s.recipients', 'r')
                ->andWhere('r.address LIKE :toSearch')
                ->setParameter('toSearch', '%' . $toSearch . '%');
        }

        if ($subjectSearch !== null) {
            $qb->andWhere('LOWER(s.subject) LIKE LOWER(:subjectSearch)')
                ->setParameter('subjectSearch', '%' . strtolower($subjectSearch) . '%');
        }

        /** @var Send[] $results */
        $results = $qb->getQuery()->getResult();

        return new ArrayCollection($results);
    }

    public function getSendByUuid(string $uuid): ?Send
    {
        return $this->em->getRepository(Send::class)->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @param Address[] $to
     * @param Address[] $cc
     * @param Address[] $bcc
     * @param array<string, string> $customHeaders
     * @param array<SendingAttachment> $attachments
     * @throws EmailTooLargeException
     */
    public function createSend(
        Project $project,
        Domain $domain,
        Queue $queue,
        Address $from,
        array $to,
        array $cc,
        array $bcc,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        array $customHeaders,
        array $attachments,
    ): Send {
        [
            'raw' => $rawEmail,
            'uuid' => $uuid,
            'messageId' => $messageId
        ] = $this->emailBuilder->build(
            $domain,
            $from,
            $to,
            $cc,
            $bcc,
            $subject,
            $bodyHtml,
            $bodyText,
            $customHeaders,
            $attachments
        );

        $send = new Send();
        $send->setUuid($uuid);
        $send->setCreatedAt($this->now());
        $send->setUpdatedAt($this->now());
        $send->setSendAfter($this->now());
        $send->setProject($project);
        $send->setDomain($domain);
        $send->setQueue($queue);
        $send->setQueueName($queue->getName());
        $send->setFromAddress($from->getAddress());
        $send->setFromName($from->getName());
        $send->setSubject($subject);
        $send->setBodyHtml($bodyHtml);
        $send->setBodyText($bodyText);
        $send->setHeaders($customHeaders);
        $send->setMessageId($messageId);
        $send->setRaw($rawEmail);
        $send->setSizeBytes(strlen($rawEmail));

        $this->em->persist($send);

        $shouldQueue = $this->recipientFactory->create(
            $send,
            [
                [SendRecipientType::TO, $to],
                [SendRecipientType::CC, $cc],
                [SendRecipientType::BCC, $bcc],
            ],
        );
        $send->setQueued($shouldQueue);

        $this->em->flush();

        return $send;
    }


    /**
     * @return array{sends_24h_count: int, recipients_24h_count: int, recipients_24h_accepted_count: int, recipients_24h_bounced_count: int, recipients_24h_complained_count: int, recipients_24h_failed_count: int, recipients_24h_suppressed_count: int}
     */
    public function getLast24HoursSendCount(): array
    {
        $conn = $this->em->getConnection();
        $sql = <<<SQL
        SELECT
            -- Sends in last 24h
            COUNT(DISTINCT s.id) AS sends_24h_count,
            
            -- Recipients by status in last 24h
            COUNT(r.id) AS recipients_24h_count,
            COUNT(CASE WHEN r.status = 'accepted'   THEN 1 END) AS recipients_24h_accepted_count,
            COUNT(CASE WHEN r.status = 'bounced'    THEN 1 END) AS recipients_24h_bounced_count,
            COUNT(CASE WHEN r.status = 'complained' THEN 1 END) AS recipients_24h_complained_count,
            COUNT(CASE WHEN r.status = 'failed'     THEN 1 END) AS recipients_24h_failed_count,
            COUNT(CASE WHEN r.status = 'suppressed' THEN 1 END) AS recipients_24h_suppressed_count
        FROM sends s
        LEFT JOIN send_recipients r ON r.send_id = s.id
        WHERE s.created_at >= NOW() - INTERVAL '24 hours';
        SQL;

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        /** @var array<string, ?int> $data */
        $data = $result->fetchAssociative();

        return [
            'sends_24h_count' => $data['sends_24h_count'] ?? 0,
            'recipients_24h_count' => $data['recipients_24h_count'] ?? 0,
            'recipients_24h_accepted_count' => $data['recipients_24h_accepted_count'] ?? 0,
            'recipients_24h_bounced_count' => $data['recipients_24h_bounced_count'] ?? 0,
            'recipients_24h_complained_count' => $data['recipients_24h_complained_count'] ?? 0,
            'recipients_24h_failed_count' => $data['recipients_24h_failed_count'] ?? 0,
            'recipients_24h_suppressed_count' => $data['recipients_24h_suppressed_count'] ?? 0,
        ];
    }


}
