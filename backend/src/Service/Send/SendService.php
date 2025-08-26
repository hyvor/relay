<?php

namespace App\Service\Send;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use App\Repository\SendRepository;
use App\Service\Send\Dto\SendingAttachment;
use App\Service\Send\Dto\SendUpdateDto;
use App\Service\Send\Exception\EmailTooLargeException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;

class SendService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EmailBuilder $emailBuilder,
        private SendRepository $sendRepository,
        private EventDispatcherInterface $eventDispatcher
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

        //dd($qb->getQuery()->getSQL());
        /** @var Send[] $results */
        $results = $qb->getQuery()->getResult();

        return new ArrayCollection($results);
    }


    public function getSendById(int $id): ?Send
    {
        return $this->em->getRepository(Send::class)->find($id);
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
        $send->setQueued(true);
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

        foreach (
            [
                [SendRecipientType::TO, $to],
                [SendRecipientType::CC, $cc],
                [SendRecipientType::BCC, $bcc],
            ]
            as [$type, $recipients]
        ) {
            foreach ($recipients as $recipient) {
                $sendRecipient = new SendRecipient();
                $sendRecipient->setSend($send);
                $sendRecipient->setStatus(SendRecipientStatus::QUEUED);
                $sendRecipient->setAddress($recipient->getAddress());
                $sendRecipient->setName($recipient->getName());
                $sendRecipient->setType($type);

                $send->addRecipient($sendRecipient);
                $this->em->persist($sendRecipient);
            }
        }

        $this->em->flush();

        return $send;
    }

    public function updateSend(Send $send, SendUpdateDto $update): Send
    {
        if ($update->statusSet) {
            $send->setStatus($update->status);
        }

        if ($update->sentAtSet) {
            $send->setSentAt($update->sentAt);
        }

        if ($update->failedAtSet) {
            $send->setFailedAt($update->failedAt);
        }

        if ($update->resultSet) {
            $send->setResult($update->result);
        }

        $send->setUpdatedAt($this->now());

        $this->em->persist($send);
        $this->em->flush();

        return $send;
    }

    /**
     * @return SendAttempt[]
     */
    public function getSendAttemptsOfSend(Send $send): array
    {
        return $this->em->getRepository(SendAttempt::class)->findBy(['send' => $send], ['id' => 'DESC']);
    }

    public function getSendAttemptById(int $id): ?SendAttempt
    {
        return $this->em->getRepository(SendAttempt::class)->find($id);
    }

    public function dispatchSendAttemptCreatedEvent(SendAttempt $sendAttempt): void
    {
        $event = new Event\SendAttemptCreatedEvent($sendAttempt);
        $this->eventDispatcher->dispatch($event);
    }

}
