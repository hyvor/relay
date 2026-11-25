<?php

namespace App\Service\Send\Event;

use App\Entity\Project;
use Symfony\Component\Mime\Address;

class SendValidatingEvent
{
    public ?string $error = null;

    /**
     * @param Address[] $recipients
     */
    public function __construct(
        public Project $project,
        public array $recipients,
    ) {}
}
