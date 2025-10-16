<?php

namespace App\Entity;

use App\Entity\Type\SendRecipientStatus;
use App\Repository\SendAttemptRecipientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SendAttemptRecipientRepository::class)]
#[ORM\Table(name: "send_attempt_recipients")]
class SendAttemptRecipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: SendAttempt::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(name: "send_attempt_id", nullable: false, onDelete: "CASCADE")]
    private SendAttempt $send_attempt;

    #[ORM\Column(type: "integer")]
    private int $send_recipient_id;

    #[ORM\Column(type: "integer")]
    private int $smtp_code;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $smtp_enhanced_code = null;

    #[ORM\Column(type: "text")]
    private string $smtp_message;

    #[ORM\Column(type: "string", enumType: SendRecipientStatus::class)]
    private SendRecipientStatus $recipient_status;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
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

    public function getSendAttempt(): SendAttempt
    {
        return $this->send_attempt;
    }

    public function setSendAttempt(SendAttempt $send_attempt): static
    {
        $this->send_attempt = $send_attempt;
        return $this;
    }

    public function getSendRecipientId(): int
    {
        return $this->send_recipient_id;
    }

    public function setSendRecipient(int $send_recipient_id): static
    {
        $this->send_recipient_id = $send_recipient_id;
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

    public function getSmtpEnhancedCode(): ?string
    {
        return $this->smtp_enhanced_code;
    }

    public function setSmtpEnhancedCode(?string $smtp_enhanced_code): static
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

    public function getRecipientStatus(): SendRecipientStatus
    {
        return $this->recipient_status;
    }

    public function setRecipientStatus(SendRecipientStatus $recipient_status): static
    {
        $this->recipient_status = $recipient_status;
        return $this;
    }
}