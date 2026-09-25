<?php

namespace App\Service\SendFeedback;

use App\Entity\DebugIncomingEmail;
use App\Entity\IpAddress;
use App\Entity\Send;
use App\Entity\SendFeedback;
use App\Entity\SendRecipient;
use App\Entity\Type\SendFeedbackType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class SendFeedbackService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return SendFeedback[]
     */
    public function getFeedbackOfSend(Send $send): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('sf')
            ->from(SendFeedback::class, 'sf')
            ->where('sf.send = :send')
            ->setParameter('send', $send);

        /** @var SendFeedback[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function createSendFeedback(
        SendFeedbackType $type,
        Send $send,
        ?SendRecipient $recipient,
        DebugIncomingEmail $debugIncomingEmail,
        ?IpAddress $ipAddress = null,
        ?string $detail = null,
    ): SendFeedback {
        $sendFeedback = new SendFeedback();
        $sendFeedback->setCreatedAt(new \DateTimeImmutable());
        $sendFeedback->setUpdatedAt(new \DateTimeImmutable());
        $sendFeedback->setType($type);
        $sendFeedback->setProject($send->getProject());
        $sendFeedback->setSend($send);
        $sendFeedback->setSendRecipient($recipient);
        $sendFeedback->setIpAddress($ipAddress);
        $sendFeedback->setDetail($detail);
        $sendFeedback->setDebugIncomingEmail($debugIncomingEmail);

        $this->em->persist($sendFeedback);
        $this->em->flush();

        return $sendFeedback;
    }
}
