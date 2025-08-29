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

    #[ORM\ManyToOne(targetEntity: SendRecipient::class)]
    #[ORM\JoinColumn]
    private SendRecipient $send_recipient;

    #[ORM\OneToOne(targetEntity: DebugIncomingEmail::class)]
    #[ORM\JoinColumn]
    private DebugIncomingEmail $debugIncomingEmail;

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

    public function getSendRecipient(): SendRecipient
    {
        return $this->send_recipient;
    }

    public function setSendRecipient(SendRecipient $send_recipient): static
    {
        $this->send_recipient = $send_recipient;
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
}

