<?php

namespace App\Api\Sudo\Object;

use App\Entity\Server;

class ServerObject
{
    public int $id;
    public int $created_at;
    public string $hostname;
    public ?int $last_ping_at;
    public bool $is_alive;
    public int $api_workers;
    public int $email_workers;
    public int $webhook_workers;
    public int $incoming_workers;

    public function __construct(Server $server)
    {
        $this->id = $server->getId();
        $this->created_at = $server->getCreatedAt()->getTimestamp();
        $this->hostname = $server->getHostname();
        $this->last_ping_at = $server->getLastPingAt()?->getTimestamp();
        $this->is_alive = ($server->getLastPingAt() ?? new \DateTimeImmutable()) > (new \DateTimeImmutable('-3 minutes'));
        $this->api_workers = $server->getApiWorkers();
        $this->email_workers = $server->getEmailWorkers();
        $this->webhook_workers = $server->getWebhookWorkers();
        $this->incoming_workers = $server->getIncomingWorkers();
    }
}
