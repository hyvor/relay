<?php

namespace App\Api\Console\Object;

use App\Entity\Webhook;

class WebhookObject
{
    public int $id;
    public string $url;
    public ?string $description;
    /**
     * @var string[]
     */
    public array $events;
    public ?string $secret;

    public function __construct(Webhook $webhook, ?string $secret = null)
    {
        $this->id = $webhook->getId();
        $this->url = $webhook->getUrl();
        $this->description = $webhook->getDescription();
        $this->events = $webhook->getEvents();
        $this->secret = $secret;
    }
}
