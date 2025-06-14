<?php

namespace App\Service\Email;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\Type\SendStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Uid\Uuid;

class SendService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function createSend(
        Project $project,
        Domain $domain,
        Queue $queue,
        string $fromAddress,
        string $toAddress,
        ?string $subject,
        ?string $bodyHtml,
        ?string $bodyText,
    ): Send
    {

        $send = new Send();
        $send->setUuid(Uuid::v4());
        $send->setCreatedAt($this->now());
        $send->setUpdatedAt($this->now());
        $send->setStatus(SendStatus::QUEUED);
        $send->setProject($project);
        $send->setDomain($domain);
        $send->setQueue($queue);
        $send->setFromAddress($fromAddress);
        $send->setToAddress($toAddress);
        $send->setSubject($subject);
        $send->setBodyHtml($bodyHtml);
        $send->setBodyText($bodyText);

        $this->em->persist($send);
        $this->em->flush();

        return $send;

    }

}