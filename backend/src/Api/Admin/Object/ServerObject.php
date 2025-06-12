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
    public int $api_workers = 0;
    public int $email_workers = 0;
    public int $webhook_workers = 0;

    public function __construct(Server $server)
    {
        $this->id = $server->getId();
        $this->created_at = $server->getCreatedAt()->getTimestamp();
        $this->hostname = $server->getHostname();
        $this->last_ping_at = $server->getLastPingAt()?->getTimestamp();
        $this->api_on = $server->getApiOn();
        $this->api_workers = $server->getApiWorkers();
        $this->email_workers = $server->getEmailWorkers();
        $this->webhook_workers = $server->getWebhookWorkers();
    }
}
