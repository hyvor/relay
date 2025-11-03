<?php

namespace App\Entity;

use App\Repository\InfrastructureBounceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InfrastructureBounceRepository::class)]
#[ORM\Table(name: "infrastructure_bounces")]
class InfrastructureBounce
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "boolean")]
    private bool $is_read;

    #[ORM\Column(type: "integer")]
    private int $smtp_code;

    #[ORM\Column(type: "text")]
    private string $smtp_enhanced_code;

    #[ORM\Column(type: "text")]
    private string $smtp_message;

    #[ORM\Column(type: "integer")]
    private int $send_recipient_id;

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

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function setIsRead(bool $is_read): static
    {
        $this->is_read = $is_read;
        return $this;
    }

    public function getSmtpCode(): int
    {
        return $this->smtp_code;
    }

    public function setSmtpCode(int $smtp_code): static
    {
        $this->smtp_code = $smtp_code;
        return $this;
    }

    public function getSmtpEnhancedCode(): string
    {
        return $this->smtp_enhanced_code;
    }

    public function setSmtpEnhancedCode(string $smtp_enhanced_code): static
    {
        $this->smtp_enhanced_code = $smtp_enhanced_code;
        return $this;
    }

    public function getSmtpMessage(): string
    {
        return $this->smtp_message;
    }

    public function setSmtpMessage(string $smtp_message): static
    {
        $this->smtp_message = $smtp_message;
        return $this;
    }

    public function getSendRecipientId(): int
    {
        return $this->send_recipient_id;
    }

    public function setSendRecipientId(int $send_recipient_id): static
    {
        $this->send_recipient_id = $send_recipient_id;
        return $this;
    }
}
