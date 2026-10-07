<?php

namespace App\Entity;

use App\Entity\Type\SendFeedbackType;
use App\Repository\SendFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SendFeedbackRepository::class)]
#[ORM\Table(name: "send_feedback")]
class SendFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "string", enumType: SendFeedbackType::class)]
    private SendFeedbackType $type;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Send::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Send $send = null;

    #[ORM\ManyToOne(targetEntity: SendRecipient::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SendRecipient $send_recipient = null;

    #[ORM\ManyToOne(targetEntity: IpAddress::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "CASCADE")]
    private ?IpAddress $ip_address = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $detail = null;

    #[ORM\OneToOne(targetEntity: DebugIncomingEmail::class)]
    #[ORM\JoinColumn]
    private DebugIncomingEmail $debugIncomingEmail;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $processed_at = null;

    #[ORM\Column(type: "date_immutable", nullable: true)]
    private ?\DateTimeImmutable $stat_date = null;

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

    public function setCreatedAt(\DateTimeImmutable $time): static
    {
        $this->created_at = $time;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $time): static
    {
        $this->updated_at = $time;
        return $this;
    }

    public function getType(): SendFeedbackType
    {
        return $this->type;
    }

    public function setType(SendFeedbackType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getSend(): ?Send
    {
        return $this->send;
    }

    public function setSend(?Send $send): static
    {
        $this->send = $send;
        return $this;
    }

    public function getSendRecipient(): ?SendRecipient
    {
        return $this->send_recipient;
    }

    public function setSendRecipient(?SendRecipient $send_recipient): static
    {
        $this->send_recipient = $send_recipient;
        return $this;
    }

    public function getIpAddress(): ?IpAddress
    {
        return $this->ip_address;
    }

    public function setIpAddress(?IpAddress $ipAddress): static
    {
        $this->ip_address = $ipAddress;
        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;
        return $this;
    }

    public function getDebugIncomingEmail(): DebugIncomingEmail
    {
        return $this->debugIncomingEmail;
    }

    public function setDebugIncomingEmail(DebugIncomingEmail $debugIncomingEmail): static
    {
        $this->debugIncomingEmail = $debugIncomingEmail;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processed_at;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processed_at = $processedAt;
        return $this;
    }

    public function getStatDate(): ?\DateTimeImmutable
    {
        return $this->stat_date;
    }

    public function setStatDate(?\DateTimeImmutable $statDate): static
    {
        $this->stat_date = $statDate;
        return $this;
    }
}

