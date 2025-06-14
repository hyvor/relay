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

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $sent_at = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $failed_at = null;

    #[ORM\Column(type: "string", enumType: SendStatus::class)]
    private SendStatus $status;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn()]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn()]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Queue::class)]
    #[ORM\JoinColumn()]
    private ?Queue $queue = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $content_html = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $content_text = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $from_address = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $to_address = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $body_html = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $body_text = null;

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

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getQueue(): ?Queue
    {
        return $this->queue;
    }

    public function setQueue(?Queue $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getContentHtml(): ?string
    {
        return $this->content_html;
    }

    public function setContentHtml(?string $content_html): static
    {
        $this->content_html = $content_html;
        return $this;
    }

    public function getContentText(): ?string
    {
        return $this->content_text;
    }

    public function setContentText(?string $content_text): static
    {
        $this->content_text = $content_text;
        return $this;
    }

    public function getFromAddress(): ?string
    {
        return $this->from_address;
    }

    public function setFromAddress(?string $from_address): static
    {
        $this->from_address = $from_address;
        return $this;
    }

    public function getToAddress(): ?string
    {
        return $this->to_address;
    }

    public function setToAddress(?string $to_address): static
    {
        $this->to_address = $to_address;
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

}
