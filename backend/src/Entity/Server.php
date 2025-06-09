<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'servers')]
#[ORM\HasLifecycleCallbacks]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: 'string', length: 255)]
    private string $hostname;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $last_ping_at = null;

    #[ORM\Column(type: 'boolean')]
    private bool $api_on = false;

    #[ORM\Column(type: 'boolean')]
    private bool $email_on = false;

    #[ORM\Column(type: 'boolean')]
    private bool $webhook_on = false;

    public function __construct()
    {}

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

    public function getLastPingAt(): ?\DateTimeImmutable
    {
        return $this->last_ping_at;
    }

    public function setLastPingAt(?\DateTimeImmutable $lastPingAt): static
    {
        $this->last_ping_at = $lastPingAt;
        return $this;
    }

    public function getApiOn(): bool
    {
        return $this->api_on;
    }

    public function setApiOn(bool $apiOn): static
    {
        $this->api_on = $apiOn;
        return $this;
    }

    public function getEmailOn(): bool
    {
        return $this->email_on;
    }

    public function setEmailOn(bool $emailOn): static
    {
        $this->email_on = $emailOn;
        return $this;
    }

    public function getWebhookOn(): bool
    {
        return $this->webhook_on;
    }

    public function setWebhookOn(bool $webhookOn): static
    {
        $this->webhook_on = $webhookOn;
        return $this;
    }
}