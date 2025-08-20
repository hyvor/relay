<?php

namespace App\Entity;

use App\Entity\Type\SendAttemptStatus;
use App\Repository\SendAttemptRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Type\SendStatus;

#[ORM\Entity(repositoryClass: SendAttemptRepository::class)]
#[ORM\Table(name: "send_attempts")]
class SendAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Send::class)]
    #[ORM\JoinColumn(name: "send_id", nullable: false, onDelete: "CASCADE")]
    private Send $send;

    #[ORM\ManyToOne(targetEntity: IpAddress::class)]
    #[ORM\JoinColumn()]
    private IpAddress $ip_address;

    /** @var string[] */
    #[ORM\Column(type: "json")]
    private array $resolved_mx_hosts = [];

    #[ORM\Column(type: "string", enumType: SendAttemptStatus::class)]
    private SendAttemptStatus $status;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $try_count = 0;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $responded_mx_host = null;

    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: "json")]
    private array $smtp_conversations = [];

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $error = null;

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

    public function getSend(): Send
    {
        return $this->send;
    }

    public function setSend(Send $send): static
    {
        $this->send = $send;
        return $this;
    }

    public function getIpAddress(): IpAddress
    {
        return $this->ip_address;
    }

    public function setIpAddress(IpAddress $ip_address): static
    {
        $this->ip_address = $ip_address;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getResolvedMxHosts(): array
    {
        return $this->resolved_mx_hosts;
    }

    /**
     * @param string[] $resolvedMxHosts
     */
    public function setResolvedMxHosts(array $resolvedMxHosts): static
    {
        $this->resolved_mx_hosts = $resolvedMxHosts;
        return $this;
    }

    public function getRespondedMxHost(): ?string
    {
        return $this->responded_mx_host;
    }

    public function setRespondedMxHost(?string $responded_mx_host): static
    {
        $this->responded_mx_host = $responded_mx_host;
        return $this;
    }

    public function getStatus(): SendAttemptStatus
    {
        return $this->status;
    }

    public function setStatus(SendAttemptStatus $status): static
    {
        $this->status = $status;
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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getSmtpConversations(): array
    {
        return $this->smtp_conversations;
    }

    /**
     * @param array<string, array<string, mixed>> $smtpConversations
     */
    public function setSmtpConversations(array $smtpConversations): static
    {
        $this->smtp_conversations = $smtpConversations;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;
        return $this;
    }
}
