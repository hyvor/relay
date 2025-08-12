<?php

namespace App\Entity;

use App\Entity\Type\DomainStatus;
use App\Repository\DomainRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: "domains")]
class Domain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn]
    private Project $project;

    #[ORM\Column(type: "string", length: 255)]
    private string $domain;

    #[ORM\Column(type: "string", enumType: DomainStatus::class)]
    private DomainStatus $status;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $status_changed_at;

    #[ORM\Column(type: "string")]
    private string $dkim_selector;

    #[ORM\Column(type: "text")]
    private string $dkim_public_key;

    #[ORM\Column(type: "text")]
    private string $dkim_private_key_encrypted;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $dkim_checked_at = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $dkim_error_message = null;

    public function __construct()
    {
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

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;
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

    public function getStatus(): DomainStatus
    {
        return $this->status;
    }

    public function setStatus(DomainStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusChangedAt(): \DateTimeImmutable
    {
        return $this->status_changed_at;
    }

    public function setStatusChangedAt(\DateTimeImmutable $statusChangedAt): static
    {
        $this->status_changed_at = $statusChangedAt;
        return $this;
    }

    public function getDkimSelector(): string
    {
        return $this->dkim_selector;
    }

    public function setDkimSelector(string $dkimSelector): static
    {
        $this->dkim_selector = $dkimSelector;
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

    public function getDkimPrivateKeyEncrypted(): string
    {
        return $this->dkim_private_key_encrypted;
    }

    public function setDkimPrivateKeyEncrypted(string $dkimPrivateKey): static
    {
        $this->dkim_private_key_encrypted = $dkimPrivateKey;
        return $this;
    }

    public function getDkimCheckedAt(): ?\DateTimeImmutable
    {
        return $this->dkim_checked_at;
    }

    public function setDkimCheckedAt(?\DateTimeImmutable $dkimCheckedAt): static
    {
        $this->dkim_checked_at = $dkimCheckedAt;
        return $this;
    }

    public function getDkimErrorMessage(): ?string
    {
        return $this->dkim_error_message;
    }

    public function setDkimErrorMessage(?string $dkimErrorMessage): static
    {
        $this->dkim_error_message = $dkimErrorMessage;
        return $this;
    }
}
