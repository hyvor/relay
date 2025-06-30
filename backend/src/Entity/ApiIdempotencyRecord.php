<?php

namespace App\Entity;

use App\Repository\ApiIdempotencyRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiIdempotencyRecordRepository::class)]
#[ORM\Table(name: 'api_idempotency_records')]
#[ORM\UniqueConstraint(name: 'unique_project_idempotency_key', columns: ['project_id', 'idempotency_key'])]
class ApiIdempotencyRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private \DateTimeImmutable $created_at;

    #[ORM\Column]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn]
    private Project $project;

    #[ORM\Column(type: 'text')]
    private string $idempotency_key;

    #[ORM\Column(type: 'text')]
    private string $endpoint;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $response = null;

    #[ORM\Column]
    private int $status_code;

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

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotency_key;
    }

    public function setIdempotencyKey(string $idempotency_key): static
    {
        $this->idempotency_key = $idempotency_key;

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }

    public function setResponse(?array $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function setStatusCode(int $status_code): static
    {
        $this->status_code = $status_code;

        return $this;
    }
}
