<?php

namespace App\Service\Email;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\Type\SendStatus;
use App\Service\Email\Dto\SendUpdateDto;
use App\Service\Email\Message\EmailSendMessage;
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
        private MessageBusInterface $bus,
    )
    {
    }

    public function getSendById(int $id): ?Send
    {
        return $this->em->getRepository(Send::class)->find($id);
    }

    public function createSend(
        Project $project,
        Domain $domain,
        Queue $queue,
        Address $from,
        Address $to,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
    ): Send
    {

        $rawEmail = $this->emailBuilder->build(
            $from,
            $to,
            $subject,
            $bodyHtml,
            $bodyText
        );

        $this->em->beginTransaction();

        try {

            $send = new Send();
            $send->setUuid(Uuid::v4());
            $send->setCreatedAt($this->now());
            $send->setUpdatedAt($this->now());
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
            $send->setRaw($rawEmail);
            $this->em->persist($send);
            $this->em->flush();

            $this->bus->dispatch(new EmailSendMessage(
                sendId: $send->getId(),
                from: $from->getAddress(),
                to: $to->getAddress(),
                rawEmail: $rawEmail
            ));

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

}