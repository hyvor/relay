<?php

namespace App\Entity;

use App\Entity\Type\DebugIncomingEmailStatus;
use App\Entity\Type\DebugIncomingEmailType;
use App\Repository\DebugIncomingEmailRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DebugIncomingEmailRepository::class)]
#[ORM\Table(name: "debug_incoming_emails")]
class DebugIncomingEmail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "string", enumType: DebugIncomingEmailType::class)]
    private DebugIncomingEmailType $type;

    #[ORM\Column(type: "string", enumType: DebugIncomingEmailStatus::class)]
    private DebugIncomingEmailStatus $status;

    #[ORM\Column(type: "text")]
    private string $raw_email;

    #[ORM\Column(type: "text")]
    private string $mail_from;

    #[ORM\Column(type: "text")]
    private string $rcpt_to;

    /**
     * @var array<mixed>|null
     */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parsed_data = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $error_message = null;

    public function __construct()
    {
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

    public function getType(): DebugIncomingEmailType
    {
        return $this->type;
    }

    public function setType(DebugIncomingEmailType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): DebugIncomingEmailStatus
    {
        return $this->status;
    }

    public function setStatus(DebugIncomingEmailStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getRawEmail(): string
    {
        return $this->raw_email;
    }

    public function setRawEmail(string $raw_email): static
    {
        $this->raw_email = $raw_email;
        return $this;
    }

    public function getMailFrom(): string
    {
        return $this->mail_from;
    }

    public function setMailFrom(string $mail_from): static
    {
        $this->mail_from = $mail_from;
        return $this;
    }

    public function getRcptTo(): string
    {
        return $this->rcpt_to;
    }

    public function setRcptTo(string $rcpt_to): static
    {
        $this->rcpt_to = $rcpt_to;
        return $this;
    }

    public function getParsedData(): ?array
    {
        return $this->parsed_data;
    }

    public function setParsedData(?array $parsed_data): static
    {
        $this->parsed_data = $parsed_data;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $error_message): static
    {
        $this->error_message = $error_message;
        return $this;
    }
}
