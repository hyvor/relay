<?php

namespace App\Entity;

use App\Entity\Type\KycBusinessType;
use App\Entity\Type\KycStatus;
use App\Repository\KycRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KycRepository::class)]
#[ORM\Table(name: 'kyc')]
class Kyc
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private \DateTimeImmutable $created_at;

    #[ORM\Column]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(unique: true)]
    private int $organization_id;

    #[ORM\Column(length: 255)]
    private string $full_name;

    #[ORM\Column(enumType: KycBusinessType::class)]
    private KycBusinessType $business_type;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $business_name = null;

    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(type: 'text')]
    private string $address;

    #[ORM\Column(length: 50)]
    private string $phone;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(enumType: KycStatus::class)]
    private KycStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $submitted_at;

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

    public function getOrganizationId(): int
    {
        return $this->organization_id;
    }

    public function setOrganizationId(int $organization_id): static
    {
        $this->organization_id = $organization_id;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->full_name;
    }

    public function setFullName(string $full_name): static
    {
        $this->full_name = $full_name;

        return $this;
    }

    public function getBusinessType(): KycBusinessType
    {
        return $this->business_type;
    }

    public function setBusinessType(KycBusinessType $business_type): static
    {
        $this->business_type = $business_type;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->business_name;
    }

    public function setBusinessName(?string $business_name): static
    {
        $this->business_name = $business_name;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getStatus(): KycStatus
    {
        return $this->status;
    }

    public function setStatus(KycStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submitted_at;
    }

    public function setSubmittedAt(\DateTimeImmutable $submitted_at): static
    {
        $this->submitted_at = $submitted_at;

        return $this;
    }
}
