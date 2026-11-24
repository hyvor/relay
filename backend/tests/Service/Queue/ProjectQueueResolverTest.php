<?php

namespace App\Tests\Service\Queue;

use App\Entity\Project;
use App\Entity\Type\ProjectSendType;
use App\Entity\Type\QueueType;
use App\Service\Queue\Event\ProjectQueueSelectingEvent;
use App\Service\Queue\ProjectQueueResolver;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\QueueFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectQueueResolver::class)]
#[CoversClass(ProjectQueueSelectingEvent::class)]
class ProjectQueueResolverTest extends KernelTestCase
{

    private ProjectQueueResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProjectQueueResolver $resolver */
        $resolver = $this->container->get(ProjectQueueResolver::class);
        $this->resolver = $resolver;
    }

    public function test_returns_cached_queue_without_dispatching(): void
    {
        $queue = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'cached']);
        QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $project = ProjectFactory::createOne(['queue' => $queue]);

        $this->assertSame($queue, $this->resolver->resolve($project));
        $this->getEd()->assertNotDispatched(ProjectQueueSelectingEvent::class);
    }

    public function test_resolves_default_queue_and_caches(): void
    {
        $transactional = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $distributional = QueueFactory::createDefault(QueueType::DISTRIBUTIONAL);

        $project = ProjectFactory::createOne(['send_type' => ProjectSendType::TRANSACTIONAL]);
        $this->assertSame($transactional, $this->resolver->resolve($project));
        $this->getEd()->assertDispatched(ProjectQueueSelectingEvent::class);

        $project2 = ProjectFactory::createOne(['send_type' => ProjectSendType::DISTRIBUTIONAL]);
        $this->assertSame($distributional, $this->resolver->resolve($project2));
        $this->getEd()->assertDispatchedCount(ProjectQueueSelectingEvent::class, 2);

        $this->em->clear();
        $saved = $this->em->getRepository(Project::class)->find($project->getId());
        $this->assertNotNull($saved);
        $this->assertSame($transactional->getId(), $saved->getQueue()?->getId());

        $saved2 = $this->em->getRepository(Project::class)->find($project2->getId());
        $this->assertNotNull($saved2);
        $this->assertSame($distributional->getId(), $saved2->getQueue()?->getId());
    }

    public function test_uses_queue_set_by_listener(): void
    {
        QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $custom = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'custom']);

        $this->getEd()->addListener(
            ProjectQueueSelectingEvent::class,
            fn(ProjectQueueSelectingEvent $event) => $event->setQueue($custom)
        );

        $project = ProjectFactory::createOne();
        $this->assertSame($custom, $this->resolver->resolve($project));
        $this->assertSame($custom, $project->getQueue());
    }

    public function test_does_not_cache_when_not_cacheable(): void
    {
        $default = QueueFactory::createDefault(QueueType::TRANSACTIONAL);

        $this->getEd()->addListener(
            ProjectQueueSelectingEvent::class,
            fn(ProjectQueueSelectingEvent $event) => $event->setCacheable(false)
        );

        $project = ProjectFactory::createOne();
        $this->assertSame($default, $this->resolver->resolve($project));
        $this->assertNull($project->getQueue());
    }

    public function test_reassign_organization(): void
    {
        $default = QueueFactory::createDefault(QueueType::TRANSACTIONAL);
        $old = QueueFactory::createOne(['type' => QueueType::TRANSACTIONAL, 'name' => 'old']);
        $dedicated = QueueFactory::createOne([
            'type' => QueueType::DEDICATED,
            'name' => 'dedicated',
            'dedicated_organization_id' => 1,
        ]);

        $trial = QueueFactory::createTrial(QueueType::TRANSACTIONAL);
        $this->getEd()->addListener(
            ProjectQueueSelectingEvent::class,
            fn(ProjectQueueSelectingEvent $event) => $event->setQueue($trial)
        );

        $project1 = ProjectFactory::createOne(['organization_id' => 1, 'queue' => $old]);
        $project2 = ProjectFactory::createOne(['organization_id' => 1]);
        $projectDedicated = ProjectFactory::createOne(['organization_id' => 1, 'queue' => $dedicated]);
        $otherOrgProject = ProjectFactory::createOne(['organization_id' => 2, 'queue' => $default]);

        $this->resolver->reassignOrganization(1);

        $this->assertSame($trial->getId(), $project1->getQueue()?->getId());
        $this->assertSame($trial->getId(), $project2->getQueue()?->getId());
        $this->assertSame($dedicated->getId(), $projectDedicated->getQueue()?->getId());
        $this->assertSame($default->getId(), $otherOrgProject->getQueue()?->getId());
    }

}
