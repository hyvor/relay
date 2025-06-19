<?php

namespace App\Entity;

use App\Entity\Type\ApiKeyScope;
use App\Repository\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_keys')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn]
    private Project $project;

    #[ORM\Column(type: "string", length: 32, unique: true)]
    private string $key;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(enumType: ApiKeyScope::class)]
    private ApiKeyScope $scope;

    #[ORM\Column()]
    private bool $is_enabled;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $last_accessed_at;


    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getScope(): ApiKeyScope
    {
        return $this->scope;
    }

    public function setScope(ApiKeyScope $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getIsEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function setIsEnabled(bool $is_enabled): self
    {
        $this->is_enabled = $is_enabled;
        return $this;
    }

    public function getLastAccessedAt(): \DateTimeImmutable
    {
        return $this->last_accessed_at;
    }

    public function setLastAccessedAt(\DateTimeImmutable $last_accessed_at): self
    {
        $this->last_accessed_at = $last_accessed_at;
        return $this;
    }
}