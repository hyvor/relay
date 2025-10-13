<?php

namespace App\Service\Send;

use App\Entity\Send;
use App\Entity\SendRecipient;
use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use App\Service\Send\Event\SendRecipientSuppressedEvent;
use App\Service\Suppression\SuppressionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;

class RecipientFactory
{

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private SuppressionService $suppressionService
    ) {
    }

    /**
     * @param array<array{0: SendRecipientType, 1: Address[]}> $recipients
     * @return bool whether to queue
     */
    public function create(Send $send, array $recipients): bool
    {
        $suppressions = $this->suppressionService->getSuppressed(
            $send->getProject(),
            $this->getEmailAddresses($recipients)
        );

        $shouldQueue = false;

        foreach ($recipients as [$type, $addresses]) {
            foreach ($addresses as $address) {
                $suppression = $suppressions[$address->getAddress()] ?? null;

                $sendRecipient = new SendRecipient();
                $sendRecipient->setSend($send);
                $sendRecipient->setStatus(
                    $suppression
                        ? SendRecipientStatus::SUPPRESSED
                        : SendRecipientStatus::QUEUED
                );
                $sendRecipient->setAddress($address->getAddress());
                $sendRecipient->setName($address->getName());
                $sendRecipient->setType($type);

                $send->addRecipient($sendRecipient);
                $this->em->persist($sendRecipient);

                if ($suppression) {
                    $event = new SendRecipientSuppressedEvent($sendRecipient, $suppression);
                    $this->ed->dispatch($event);
                } else {
                    $shouldQueue = true;
                }
            }
        }

        return $shouldQueue;
    }

    /**
     * @param array<array{0: SendRecipientType, 1: Address[]}> $recipients
     * @return string[]
     */
    private function getEmailAddresses(array $recipients): array
    {
        $emailAddresses = [];

        foreach ($recipients as [$type, $addresses]) {
            foreach ($addresses as $address) {
                $emailAddresses[] = $address->getAddress();
            }
        }

        return $emailAddresses;
    }

}
