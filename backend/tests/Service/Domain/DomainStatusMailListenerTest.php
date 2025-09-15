<?php

namespace App\Tests\Service\Domain;

use App\Entity\Type\DomainStatus;
use App\Service\Domain\Event\DomainStatusChangedEvent;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\DomainFactory;

class DomainStatusMailListenerTest extends KernelTestCase
{

    public function test_pending_to_active(): void
    {
        $this->markTestSkipped();
//        $domain = DomainFactory::createOne();
//        $event = new DomainStatusChangedEvent(
//            $domain,
//            DomainStatus::PENDING,
//            DomainStatus::ACTIVE,
//        );
//
//        $this->ed->dispatch($event);
    }

}
