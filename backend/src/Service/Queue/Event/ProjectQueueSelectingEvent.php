<?php

namespace App\Service\Queue\Event;

use App\Entity\Project;
use App\Entity\Queue;

/**
 * Dispatched when the queue of a project is (re)selected.
 * Listeners may set a queue. If no queue is set, the default selection is used.
 */
class ProjectQueueSelectingEvent
{

    private ?Queue $queue = null;

    /**
     * When false, the selected queue is used for the current send only and is not
     * cached at the project
     */
    private bool $cacheable = true;

    public function __construct(
        private Project $project,
    ) {}

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getQueue(): ?Queue
    {
        return $this->queue;
    }

    public function setQueue(Queue $queue): void
    {
        $this->queue = $queue;
    }

    public function isCacheable(): bool
    {
        return $this->cacheable;
    }

    public function setCacheable(bool $cacheable): void
    {
        $this->cacheable = $cacheable;
    }
}
