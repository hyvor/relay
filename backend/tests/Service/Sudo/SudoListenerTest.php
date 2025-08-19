<?php

namespace App\Tests\Service\Sudo;

use App\Service\Sudo\SudoListener;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use Hyvor\Internal\Auth\AuthUser;
use Hyvor\Internal\Auth\Event\UserSignedUpEvent;
use Hyvor\Internal\Bundle\Entity\SudoUser;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(SudoListener::class)]
class SudoListenerTest extends KernelTestCase
{

    public function test_on_user_signup(): void
    {
        $instance = InstanceFactory::createOne();

        /** @var EventDispatcherInterface $ed */
        $ed = $this->container->get(EventDispatcherInterface::class);

        $authUser = AuthUser::fromArray([
            'id' => 12,
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@hyvor.com'
        ]);

        $ed->dispatch(new UserSignedUpEvent($authUser));

        $this->assertTrue($instance->getSudoInitialized());

        $sudoUsers = $this->em->getRepository(SudoUser::class)->findAll();
        $this->assertCount(1, $sudoUsers);
        $sudoUser = $sudoUsers[0];
        $this->assertSame(12, $sudoUser->getUserId());
    }

}