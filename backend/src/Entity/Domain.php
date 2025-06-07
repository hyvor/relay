<?php

namespace App\Entity;

use App\Repository\DomainRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'domains')]
#[ORM\HasLifecycleCallbacks]
class Domain
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
    private int $hyvor_user_id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $domain;

    #[ORM\Column(type: 'text')]
    private string $dkim_public_key;

    #[ORM\Column(type: 'text')]
    private string $dkim_private_key;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updated_at = new \DateTimeImmutable();
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

    public function getHyvorUserId(): int
    {
        return $this->hyvor_user_id;
    }

    public function setHyvorUserId(int $hyvorUserId): static
    {
        $this->hyvor_user_id = $hyvorUserId;
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDkimPublicKey(): string
    {
        return $this->dkim_public_key;
    }

    public function setDkimPublicKey(string $dkimPublicKey): static
    {
        $this->dkim_public_key = $dkimPublicKey;
        return $this;
    }

    public function getDkimPrivateKey(): ?string
    {
        return $this->dkim_private_key;
    }

    public function setDkimPrivateKey(?string $dkimPrivateKey): static
    {
        $this->dkim_private_key = $dkimPrivateKey;
        return $this;
    }
}