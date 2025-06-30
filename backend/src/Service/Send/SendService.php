<?php

namespace App\Service\Send;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\SendAttempt;
use App\Entity\Type\SendStatus;
use App\Repository\SendRepository;
use App\Service\Send\Dto\SendingAttachment;
use App\Service\Send\Dto\SendUpdateDto;
use App\Service\Send\Exception\EmailTooLargeException;
use App\Service\Send\Message\EmailSendMessage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

class SendService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EmailBuilder $emailBuilder,
        private SendRepository $sendRepository,
    )
    {
    }

    /**
     * @return ArrayCollection<int, Send>
     */
    public function getSends(
        Project $project,
        ?SendStatus $status,
        ?string $fromSearch,
        ?string $toSearch,
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
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $status->value);
        }

        if ($fromSearch !== null) {
            $qb->andWhere('s.from_address LIKE :fromSearch')
                ->setParameter('fromSearch', '%' . $fromSearch . '%');
        }

        if ($toSearch !== null) {
            $qb->andWhere('s.to_address LIKE :toSearch')
                ->setParameter('toSearch', '%' . $toSearch . '%');
        }

        // dd($qb->getQuery()->getSQL());
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
     * @param array<string, string> $customHeaders
     * @param array<SendingAttachment> $attachments
     * @throws EmailTooLargeException
     */
    public function createSend(
        Project $project,
        Domain $domain,
        Queue $queue,
        Address $from,
        Address $to,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
        array $customHeaders,
        array $attachments,
    ): Send
    {

        [
            'raw' => $rawEmail,
            'messageId' => $messageId
        ] = $this->emailBuilder->build(
            $domain,
            $from,
            $to,
            $subject,
            $bodyHtml,
            $bodyText,
            $customHeaders,
            $attachments
        );

        $this->em->beginTransaction();

        try {

            $send = new Send();
            $send->setUuid(Uuid::v4());
            $send->setCreatedAt($this->now());
            $send->setUpdatedAt($this->now());
            $send->setSendAfter($this->now());
            $send->setStatus(SendStatus::QUEUED);
            $send->setProject($project);
            $send->setDomain($domain);
            $send->setQueue($queue);
            $send->setFromAddress($from->getAddress());
            $send->setFromName($from->getName());
            $send->setToAddress($to->getAddress());
            $send->setToName($to->getName());
            $send->setSubject($subject);
            $send->setBodyHtml($bodyHtml);
            $send->setBodyText($bodyText);
            $send->setHeaders($customHeaders);
            $send->setMessageId($messageId);
            $send->setRaw($rawEmail);
            $this->em->persist($send);
            $this->em->flush();

            $this->em->commit();
            return $send;

        } catch (\Throwable $e) {

            $this->em->rollback();
            throw $e;

        }

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

}