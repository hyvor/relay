<?php

namespace App\Service\Domain;

use App\Entity\Domain;
use App\Entity\Type\DomainStatus;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DomainStatusChangedEvent::class, method: 'onDomainStatusChanged')]
class DomainStatusMailListener
{

    public function onDomainStatusChanged(DomainStatusChangedEvent $event): void
    {
        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;
        $domain = $event->domain;

        if ($oldStatus === DomainStatus::PENDING && $newStatus === DomainStatus::ACTIVE) {
            $this->sendVerified($domain);
            return;
        }

        if ($oldStatus === DomainStatus::ACTIVE && $newStatus === DomainStatus::WARNING) {
            $this->sendWarned($domain, $event->result);
            return;
        }

        if ($oldStatus === DomainStatus::WARNING && $newStatus === DomainStatus::PENDING) {
            $this->sendUnverified($domain, $event->result);
            return;
        }
    }

    private function sendVerified(Domain $domain): void
    {
        //
    }

}