<?php

namespace App\Entity;

use App\Repository\DnsRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DnsRecordRepository::class)]
#[ORM\Table(name: "dns_records")]
class DnsRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "string", length: 10)]
    private string $type;

    #[ORM\Column(type: "text")]
    private string $subdomain;

    #[ORM\Column(type: "text")]
    private string $content;

    #[ORM\Column(type: "integer")]
    private int $ttl = 3600;

    #[ORM\Column(type: "integer")]
    private int $priority = 0;

    public function __construct() {}

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->created_at = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updated_at = $updatedAt;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): static
    {
        $this->subdomain = $subdomain;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): static
    {
        $this->ttl = $ttl;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }
}
