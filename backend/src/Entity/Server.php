<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: "servers")]
#[ORM\HasLifecycleCallbacks]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    // hostname of the server
    #[ORM\Column(type: "string", length: 255)]
    private string $hostname;

    #[ORM\Column(type: "string")]
    private ?string $private_ip = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $last_ping_at = null;

    #[ORM\Column(type: "integer")]
    private int $api_workers = 0;

    #[ORM\Column(type: "integer")]
    private int $email_workers = 0; // per IP

    #[ORM\Column(type: "integer")]
    private int $webhook_workers = 0;

    #[ORM\Column(type: "integer")]
    private int $incoming_workers = 0;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $time): static
    {
        $this->created_at = $time;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $time): static
    {
        $this->updated_at = $time;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function setHostname(string $hostname): static
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function getPrivateIp(): ?string
    {
        return $this->private_ip;
    }

    public function setPrivateIp(?string $privateIp): static
    {
        $this->private_ip = $privateIp;
        return $this;
    }

    public function getLastPingAt(): ?\DateTimeImmutable
    {
        return $this->last_ping_at;
    }

    public function setLastPingAt(?\DateTimeImmutable $lastPingAt): static
    {
        $this->last_ping_at = $lastPingAt;
        return $this;
    }

    public function getApiWorkers(): int
    {
        return $this->api_workers;
    }

    public function setApiWorkers(int $apiWorkers): static
    {
        $this->api_workers = $apiWorkers;
        return $this;
    }

    public function getEmailWorkers(): int
    {
        return $this->email_workers;
    }

    public function setEmailWorkers(int $emailWorkers): static
    {
        $this->email_workers = $emailWorkers;
        return $this;
    }

    public function getWebhookWorkers(): int
    {
        return $this->webhook_workers;
    }

    public function setWebhookWorkers(int $webhookWorkers): static
    {
        $this->webhook_workers = $webhookWorkers;
        return $this;
    }

    public function getIncomingWorkers(): int
    {
        return $this->incoming_workers;
    }

    public function setIncomingWorkers(int $incomingWorkers): static
    {
        $this->incoming_workers = $incomingWorkers;
        return $this;
    }
}
