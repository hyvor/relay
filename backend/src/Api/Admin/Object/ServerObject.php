<?php

namespace App\Api\Admin\Object;

use App\Entity\Server;

class ServerObject
{

    public int $id;
    public int $created_at;
    public string $hostname;
    public ?int $last_ping_at = null;
    public bool $api_on = false;
    public bool $email_on = false;
    public bool $webhook_on = false;

    public function __construct(Server $server)
    {
        $this->id = $server->getId();
        $this->created_at = $server->getCreatedAt()->getTimestamp();
        $this->hostname = $server->getHostname();
        $this->last_ping_at = $server->getLastPingAt()?->getTimestamp();
        $this->api_on = $server->getApiOn();
        $this->email_on = $server->getEmailOn();
        $this->webhook_on = $server->getWebhookOn();
    }

}