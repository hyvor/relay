<?php

namespace App\Entity;

use App\Entity\Type\SendStatus;
use App\Repository\SendRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SendRepository::class)]
#[ORM\Table(name: "sends")]
class Send
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", unique: true)]
    private string $uuid;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $send_after;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $sent_at = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $failed_at = null;

    #[ORM\Column(type: "string", enumType: SendStatus::class)]
    private SendStatus $status;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn]
    private Domain $domain;

    #[ORM\ManyToOne(targetEntity: Queue::class)]
    #[ORM\JoinColumn]
    private Queue $queue;

    #[ORM\Column(type: "string")]
    private string $from_address;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $from_name = null;

    #[ORM\Column(type: "text")]
    private string $to_address;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $to_name = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $body_html = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $body_text = null;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: "json")]
    private array $headers = [];

    #[ORM\Column(type: "text")]
    private string $message_id;

    #[ORM\Column(type: "text")]
    private string $raw;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $result = null;

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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;
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

    public function getSendAfter(): \DateTimeImmutable
    {
        return $this->send_after;
    }

    public function setSendAfter(\DateTimeImmutable $send_after): static
    {
        $this->send_after = $send_after;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sent_at;
    }

    public function setSentAt(?\DateTimeImmutable $sent_at): static
    {
        $this->sent_at = $sent_at;
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

    public function getStatus(): SendStatus
    {
        return $this->status;
    }

    public function setStatus(SendStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function setDomain(Domain $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getQueue(): Queue
    {
        return $this->queue;
    }

    public function setQueue(Queue $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function getFromAddress(): string
    {
        return $this->from_address;
    }

    public function setFromAddress(string $from_address): static
    {
        $this->from_address = $from_address;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->from_name;
    }

    public function setFromName(?string $from_name): static
    {
        $this->from_name = $from_name;
        return $this;
    }

    public function getToAddress(): string
    {
        return $this->to_address;
    }

    public function setToAddress(string $to_address): static
    {
        $this->to_address = $to_address;
        return $this;
    }

    public function getToName(): ?string
    {
        return $this->to_name;
    }

    public function setToName(?string $to_name): static
    {
        $this->to_name = $to_name;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->body_html;
    }

    public function setBodyHtml(?string $body_html): static
    {
        $this->body_html = $body_html;
        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->body_text;
    }

    public function setBodyText(?string $body_text): static
    {
        $this->body_text = $body_text;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function getMessageId(): string
    {
        return $this->message_id;
    }

    public function setMessageId(string $message_id): static
    {
        $this->message_id = $message_id;
        return $this;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function setRaw(string $raw): static
    {
        $this->raw = $raw;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;
        return $this;
    }

}
