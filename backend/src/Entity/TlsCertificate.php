<?php

namespace App\Entity;

use App\Entity\Type\TlsCertificateStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tls_certificates')]
class TlsCertificate
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(length: 255)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(type: 'string', enumType: TlsCertificateStatus::class)]
    private TlsCertificateStatus $status;

    #[ORM\Column(type: 'text')]
    private string $private_key_encrypted;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $certificate = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $valid_from = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $valid_to = null;

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

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;
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

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getStatus(): TlsCertificateStatus
    {
        return $this->status;
    }

    public function setStatus(TlsCertificateStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPrivateKeyEncrypted(): string
    {
        return $this->private_key_encrypted;
    }

    public function setPrivateKeyEncrypted(string $private_key_encrypted): static
    {
        $this->private_key_encrypted = $private_key_encrypted;
        return $this;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function setCertificate(?string $certificate): static
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->valid_from;
    }

    public function setValidFrom(?\DateTimeImmutable $valid_from): static
    {
        $this->valid_from = $valid_from;
        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->valid_to;
    }

    public function setValidTo(?\DateTimeImmutable $valid_to): static
    {
        $this->valid_to = $valid_to;
        return $this;
    }

}