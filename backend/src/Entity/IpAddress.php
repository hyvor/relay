<?php

namespace App\Entity;

use App\Repository\IpAddressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IpAddressRepository::class)]
#[ORM\Table(name: 'ip_addresses')]
#[ORM\HasLifecycleCallbacks]
class IpAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $server_id;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn()]
    private Server $server;

    #[ORM\Column(type: 'string', length: 45)]
    private string $ip_address;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email_queue;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_active = false;

    #[ORM\Column(type: 'boolean')]
    private bool $is_enabled = true;

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

    public function getServerId(): int
    {
        return $this->server_id;
    }

    public function setServerId(int $serverId): static
    {
        $this->server_id = $serverId;
        return $this;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function setServer(Server $server): static
    {
        $this->server = $server;
        $this->server_id = $server->getId();
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ip_address;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ip_address = $ipAddress;
        return $this;
    }

    public function getEmailQueue(): ?string
    {
        return $this->email_queue;
    }

    public function setEmailQueue(?string $emailQueue): static
    {
        $this->email_queue = $emailQueue;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->is_active = $isActive;
        return $this;
    }

    public function getIsEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->is_enabled = $isEnabled;
        return $this;
    }
}