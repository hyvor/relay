<?php

namespace App\Service\Sudo;

use App\Service\Instance\InstanceService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Auth\Event\UserSignedUpEvent;
use Hyvor\Internal\Sudo\SudoUserService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(UserSignedUpEvent::class, method: 'onUserSignedUp')]
class SudoListener
{

    public function __construct(
        private InstanceService $instanceService,
        private SudoUserService $sudoUserService,
        private EntityManagerInterface $em,
    ) {
    }

    public function onUserSignedUp(UserSignedUpEvent $event): void
    {
        $user = $event->getUser();
        $instance = $this->instanceService->getInstance();

        if ($instance->getSudoInitialized() === false) {
            $instance->setSudoInitialized(true);
            $this->em->persist($instance);
            $this->em->flush();

            // currently sudoUserService does not support not flushing
            // so, two transactions are used
            // To be safe, we first update sudo_initialized in the instance
            $this->sudoUserService->create($user->id);
        }
    }

}