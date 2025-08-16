<?php

namespace App\Entity;

use App\Entity\Type\SendRecipientStatus;
use App\Entity\Type\SendRecipientType;
use App\Repository\SendRecipientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SendRecipientRepository::class)]
#[ORM\Table(name: "send_recipients")]
class SendRecipient
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Send::class)]
    #[ORM\JoinColumn]
    private Send $send;

    #[ORM\Column(type: "string", enumType: SendRecipientType::class)]
    private SendRecipientType $type;

    #[ORM\Column(type: "string")]
    private string $address;

    #[ORM\Column(type: "string")]
    private string $name;

    #[ORM\Column(type: "string", enumType: SendRecipientStatus::class)]
    private SendRecipientStatus $status;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $accepted_at = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $bounced_at = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $failed_at = null;

    #[ORM\Column(type: "integer")]
    private int $try_count = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getSend(): Send
    {
        return $this->send;
    }

    public function setSend(Send $send): static
    {
        $this->send = $send;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): SendRecipientType
    {
        return $this->type;
    }

    public function setType(SendRecipientType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): SendRecipientStatus
    {
        return $this->status;
    }

    public function setStatus(SendRecipientStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->accepted_at;
    }

    public function setAcceptedAt(?\DateTimeImmutable $accepted_at): static
    {
        $this->accepted_at = $accepted_at;
        return $this;
    }

    public function getBouncedAt(): ?\DateTimeImmutable
    {
        return $this->bounced_at;
    }

    public function setBouncedAt(?\DateTimeImmutable $bounced_at): static
    {
        $this->bounced_at = $bounced_at;
        return $this;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failed_at;
    }

    public function setFailedAt(?\DateTimeImmutable $failed_at): static
    {
        $this->failed_at = $failed_at;
        return $this;
    }

    public function getTryCount(): int
    {
        return $this->try_count;
    }

    public function setTryCount(int $try_count): static
    {
        $this->try_count = $try_count;
        return $this;
    }

}