<?php

namespace App\Entity;

use App\Entity\Type\SendStatus;
use App\Repository\SendRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column()]
    private bool $queued = true;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $send_after;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn]
    private Domain $domain;

    #[ORM\ManyToOne(targetEntity: Queue::class)]
    #[ORM\JoinColumn]
    private Queue $queue;

    #[ORM\Column()]
    private string $queue_name; // denormalized for easier access

    #[ORM\Column(type: "string")]
    private string $from_address;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $from_name = null;

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

    /**
     * @var Collection<int, SendRecipient>
     */
    #[ORM\OneToMany(targetEntity: SendRecipient::class, mappedBy: 'send')]
    private Collection $recipients;


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

    public function getQueued(): bool
    {
        return $this->queued;
    }

    public function setQueued(bool $queued): static
    {
        $this->queued = $queued;
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

    public function getQueueName(): string
    {
        return $this->queue_name;
    }

    public function setQueueName(string $queue_name): static
    {
        $this->queue_name = $queue_name;
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

    /**
     * @return SendRecipient[]
     */
    public function getRecipients(): array
    {
        /** @var SendRecipient[] $recipients */
        $recipients = $this->recipients->toArray();
        return $recipients;
    }

    public function addRecipient(SendRecipient $recipient): static
    {
        $this->recipients[] = $recipient;
        return $this;
    }

}
