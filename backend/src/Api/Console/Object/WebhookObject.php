<?php

namespace App\Api\Console\Object;

use App\Entity\Webhook;

class WebhookObject
{
    public int $id;
    public string $url;
    public string $description;
    public array $events;

    public function __construct(Webhook $webhook)
    {
        $this->id = $webhook->getId();
        $this->url = $webhook->getUrl();
        $this->description = $webhook->getDescription();
        $this->events = $webhook->getEvents();
    }
}