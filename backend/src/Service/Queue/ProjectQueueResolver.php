<?php

namespace App\Service\Queue;

use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Type\QueueType;
use App\Service\Project\ProjectService;
use App\Service\Queue\Event\ProjectQueueSelectingEvent;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Billing\License\Resolved\ResolvedLicense;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProjectQueueResolver
{

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private QueueService $queueService,
        private ProjectService $projectService,
    ) {
    }

    public function resolve(Project $project): Queue
    {
        return $project->getQueue() ?? $this->assign($project);
    }

    public function assign(Project $project, ?ResolvedLicense $license = null, bool $flush = true): Queue
    {
        $event = new ProjectQueueSelectingEvent($project, $license);
        $this->ed->dispatch($event);

        $queue = $event->getQueue() ?? $this->queueService->selectDefaultQueue(
            QueueType::fromProjectSendType($project->getSendType()),
            $project->getId(),
        );

        if ($event->isCacheable()) {
            $project->setQueue($queue);

            if ($flush) {
                $this->em->flush();
            }
        }

        return $queue;
    }

    public function reassignOrganization(int $organizationId, ?ResolvedLicense $license = null): void
    {
        $projects = $this->projectService->getProjectsByOrganizationId($organizationId);

        foreach ($projects as $project) {
            if ($project->getQueue()?->getType() === QueueType::DEDICATED) {
                continue;
            }

            $this->assign($project, $license, flush: false);
        }

        $this->em->flush();
    }

}
