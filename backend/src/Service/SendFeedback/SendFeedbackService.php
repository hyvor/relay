<?php

namespace App\Service\SendFeedback;

use App\Entity\DebugIncomingEmail;
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

    public function createSendFeedback(
        SendFeedbackType $type,
        SendRecipient $recipient,
        DebugIncomingEmail $debugIncomingEmail
    ): SendFeedback
    {
        $sendFeedback = new SendFeedback();
        $sendFeedback->setCreatedAt(new \DateTimeImmutable());
        $sendFeedback->setUpdatedAt(new \DateTimeImmutable());
        $sendFeedback->setType($type);
        $sendFeedback->setSendRecipient($recipient);
        $sendFeedback->setDebugIncomingEmail($debugIncomingEmail);

        $this->em->persist($sendFeedback);
        $this->em->flush();

        return $sendFeedback;
    }
}
