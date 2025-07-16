<?php

namespace App\Api\Console\Object;

use App\Entity\WebhookDelivery;

class WebhookDeliveryObject
{
    public int $id;
    public string $url;
    public string $event;
    public string $status;
    public ?string $response;
    public int $created_at;

    public function __construct(WebhookDelivery $webhookDelivery)
    {
        $this->id = $webhookDelivery->getId();
        $this->url = $webhookDelivery->getUrl();
        $this->event = $webhookDelivery->getEvent()->value;
        $this->status = $webhookDelivery->getStatus()->value;
        $this->response = $webhookDelivery->getResponse();
        $this->created_at = $webhookDelivery->getCreatedAt()->getTimestamp();
    }
}
