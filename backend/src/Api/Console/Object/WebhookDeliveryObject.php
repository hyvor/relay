<?php

namespace App\Api\Console\Object;

use App\Entity\Type\WebhookDeliveryStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\WebhookDelivery;

class WebhookDeliveryObject
{
    public int $id;
    public int $created_at;
    public string $url;
    public WebhooksEventEnum $event;
    public WebhookDeliveryStatus $status;
    public ?string $response;

    public function __construct(WebhookDelivery $webhookDelivery)
    {
        $this->id = $webhookDelivery->getId();
        $this->created_at = $webhookDelivery->getCreatedAt()->getTimestamp();
        $this->url = $webhookDelivery->getUrl();
        $this->event = $webhookDelivery->getEvent();
        $this->status = $webhookDelivery->getStatus();
        $this->response = $webhookDelivery->getResponse();
    }
}
