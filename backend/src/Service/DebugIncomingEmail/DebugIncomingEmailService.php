<?php

namespace App\Service\DebugIncomingEmail;

use App\Entity\DebugIncomingEmail;
use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class DebugIncomingEmailService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<mixed>|null $parsedData
     */
    public function createDebugIncomingEmail(
        DebugIncomingEmailType $type,
        DebugIncomingEmailStatus $status,
        string $rawEmail,
        string $mailFrom,
        string $rcptTo,
        ?array $parsedData = null,
        ?string $errorMessage = null
    ): DebugIncomingEmail
    {
        $debugIncomingEmail = new DebugIncomingEmail();
        $debugIncomingEmail->setCreatedAt($this->now());
        $debugIncomingEmail->setUpdatedAt($this->now());
        $debugIncomingEmail->setType($type);
        $debugIncomingEmail->setStatus($status);
        $debugIncomingEmail->setRawEmail($rawEmail);
        $debugIncomingEmail->setMailFrom($mailFrom);
        $debugIncomingEmail->setRcptTo($rcptTo);
        $debugIncomingEmail->setParsedData($parsedData);
        $debugIncomingEmail->setErrorMessage($errorMessage);

        $this->em->persist($debugIncomingEmail);
        $this->em->flush();

        return $debugIncomingEmail;
    }
}
