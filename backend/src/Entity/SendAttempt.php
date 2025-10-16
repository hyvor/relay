<?php

namespace App\Entity;

use App\Entity\Type\SendAttemptStatus;
use App\Repository\SendAttemptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;


/**
 * @phpstan-type RecipientResult array{recipient_id: int}
 */
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

    #[ORM\Column()]
    private string $domain;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $responded_mx_host = null;

    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: "json")]
    private array $smtp_conversations = [];

    /**
     * @var RecipientResult[]
     */
    #[ORM\Column(type: "json")]
    private array $recipient_results = [];

    /**
     * @var ArrayCollection<int, SendAttemptRecipient>
     */
    #[ORM\OneToMany(targetEntity: SendAttemptRecipient::class, mappedBy: 'send_attempt')]
    private ArrayCollection $recipients;

    #[ORM\Column()]
    private int $duration_ms;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $error = null;

    public function __construct()
    {
        $this->recipients = new ArrayCollection();
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

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
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

    /**
     * @return RecipientResult[]
     */
    public function getRecipientResults(): array
    {
        return $this->recipient_results;
    }

    /**
     * @param RecipientResult[] $recipientResults
     */
    public function setRecipientResults(array $recipientResults): static
    {
        $this->recipient_results = $recipientResults;
        return $this;
    }

    public function getDurationMs(): int
    {
        return $this->duration_ms;
    }

    public function setDurationMs(int $duration_ms): static
    {
        $this->duration_ms = $duration_ms;
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

    /**
     * @return ArrayCollection<int, SendAttemptRecipient>
     */
    public function getRecipients(): ArrayCollection
    {
        return $this->recipients;
    }

    public function addRecipient(SendAttemptRecipient $recipient): static
    {
        if (!$this->recipients->contains($recipient)) {
            $this->recipients->add($recipient);
            $recipient->setSendAttempt($this);
        }

        return $this;
    }

    public function removeRecipient(SendAttemptRecipient $recipient): static
    {
        $this->recipients->removeElement($recipient);

        return $this;
    }
}
