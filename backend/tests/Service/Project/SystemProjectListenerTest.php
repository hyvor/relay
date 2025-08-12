<?php

namespace App\Tests\Service\Project;

use App\Tests\Case\KernelTestCase;
use Hyvor\Internal\Bundle\Entity\SudoUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Hyvor\Internal\Sudo\Event\SudoAddedEvent;

class SystemProjectListenerTest extends KernelTestCase
{

    public function test_when_sudo_added(): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);

        $sudoUser = new SudoUser();
        $sudoUser->setUserId(1);

        $eventDispatcher->dispatch(new SudoAddedEvent($sudoUser));
    }

}