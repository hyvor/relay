<?php

namespace App\Tests\Service\Project;

use App\Entity\ProjectUser;
use App\Service\Domain\DomainService;
use App\Service\Project\SystemProjectListener;
use App\Service\ProjectUser\ProjectUserService;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\InstanceFactory;
use App\Tests\Factory\ProjectUserFactory;
use Hyvor\Internal\Bundle\Entity\SudoUser;
use Hyvor\Internal\Sudo\SudoUserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Hyvor\Internal\Sudo\Event\SudoAddedEvent;
use Hyvor\Internal\Sudo\Event\SudoRemovedEvent;

#[CoversClass(SystemProjectListener::class)]
#[CoversClass(ProjectUserService::class)]
#[CoversClass(DomainService::class)]
class SystemProjectListenerTest extends KernelTestCase
{

    #[TestWith([SudoAddedEvent::class])]
    #[TestWith([SudoRemovedEvent::class])]
    public function test_when_sudo_added_or_removed(string $event): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);

        $instance = InstanceFactory::createOne();
        $projectUser = ProjectUserFactory::createOne(['project' => $instance->getSystemProject()]);
        $otherProjectUser = ProjectUserFactory::createOne();

        $sudoUser0 = new SudoUser();
        $sudoUser0->setUserId(0);

        $sudoUser1 = new SudoUser();
        $sudoUser1->setUserId(1);

        $sudoUserService = $this->createMock(SudoUserService::class);
        $sudoUserService->expects($this->once())
            ->method('getAll')
            ->willReturn([$sudoUser0, $sudoUser1]);
        $this->container->set(SudoUserService::class, $sudoUserService);

        $eventDispatcher->dispatch(new $event($sudoUser1));

        $projectUsers = $this->em->getRepository(ProjectUser::class)->findBy(
            ['project' => $instance->getSystemProject()]
        );
        $this->assertCount(2, $projectUsers);

        $pu1 = $projectUsers[0];
        $pu2 = $projectUsers[1];

        $this->assertSame($instance->getSystemProject()->getId(), $pu1->getProject()->getId());
        $this->assertSame(0, $pu1->getUserId());
        $this->assertContains('project.read', $pu1->getScopes());
        $this->assertNotContains('project.write', $pu1->getScopes());

        $this->assertSame($instance->getSystemProject()->getId(), $pu2->getProject()->getId());
        $this->assertSame(1, $pu2->getUserId());
        $this->assertContains('project.read', $pu2->getScopes());
        $this->assertContains('sends.read', $pu2->getScopes());

        $this->assertCount(
            3,
            $this->em->getRepository(ProjectUser::class)->findAll()
        );
    }
}