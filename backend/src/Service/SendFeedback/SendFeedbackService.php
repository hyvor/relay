<?php

namespace App\Service\SendFeedback;

use App\Entity\DebugIncomingEmail;
use App\Entity\Send;
use App\Entity\SendFeedback;
use App\Entity\SendRecipient;
use App\Entity\Type\BounceReason;
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
            ->join('sf.send_recipient', 'sr')
            ->where('sr.send = :send')
            ->setParameter('send', $send);

        /** @var SendFeedback[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function hasComplaint(SendRecipient $recipient): bool
    {
        $feedback = $this->em->getRepository(SendFeedback::class)->findOneBy([
            'type' => SendFeedbackType::COMPLAINT,
            'send_recipient' => $recipient,
        ]);

        return $feedback !== null;
    }

    public function createSendFeedback(
        SendFeedbackType $type,
        SendRecipient $recipient,
        DebugIncomingEmail $debugIncomingEmail,
        ?string $detail = null,
        ?BounceReason $bounceReason = null,
    ): SendFeedback {
        $sendFeedback = new SendFeedback();
        $sendFeedback->setCreatedAt($this->now());
        $sendFeedback->setUpdatedAt($this->now());
        $sendFeedback->setType($type);
        $sendFeedback->setSendRecipient($recipient);
        $sendFeedback->setDetail($detail);
        $sendFeedback->setBounceReason($bounceReason);
        $sendFeedback->setDebugIncomingEmail($debugIncomingEmail);

        $this->em->persist($sendFeedback);
        $this->em->flush();

        return $sendFeedback;
    }
}
