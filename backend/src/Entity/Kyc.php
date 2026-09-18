<?php

namespace App\Entity;

use App\Entity\Type\KycAccountType;
use App\Entity\Type\KycContentOwnership;
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

    #[ORM\Column]
    private int $organization_id;

    #[ORM\Column(enumType: KycAccountType::class)]
    private KycAccountType $account_type;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $country;

    #[ORM\Column(type: 'text')]
    private string $address;

    #[ORM\Column(length: 255)]
    private string $website;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(enumType: KycContentOwnership::class)]
    private KycContentOwnership $content_ownership;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $sending_type = [];

    #[ORM\Column(type: 'text')]
    private string $use_case;

    #[ORM\Column(enumType: KycStatus::class)]
    private KycStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reject_reason = null;

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

    public function getAccountType(): KycAccountType
    {
        return $this->account_type;
    }

    public function setAccountType(KycAccountType $account_type): static
    {
        $this->account_type = $account_type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getWebsite(): string
    {
        return $this->website;
    }

    public function setWebsite(string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getContentOwnership(): KycContentOwnership
    {
        return $this->content_ownership;
    }

    public function setContentOwnership(KycContentOwnership $content_ownership): static
    {
        $this->content_ownership = $content_ownership;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getSendingType(): array
    {
        return $this->sending_type;
    }

    /**
     * @param string[] $sending_type
     */
    public function setSendingType(array $sending_type): static
    {
        $this->sending_type = $sending_type;

        return $this;
    }

    public function getUseCase(): string
    {
        return $this->use_case;
    }

    public function setUseCase(string $use_case): static
    {
        $this->use_case = $use_case;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->reject_reason;
    }

    public function setRejectReason(?string $reject_reason): static
    {
        $this->reject_reason = $reject_reason;

        return $this;
    }
}
