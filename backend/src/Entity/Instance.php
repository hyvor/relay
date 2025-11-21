<?php

namespace App\Entity;

use App\Repository\InstanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstanceRepository::class)]
#[ORM\Table(name: "instances")]
class Instance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: "string")]
    private string $uuid;

    #[ORM\Column(type: "text")]
    private string $dkim_public_key;

    #[ORM\Column(type: "text")]
    private string $dkim_private_key_encrypted;

    #[ORM\OneToOne(targetEntity: Project::class)]
    private Project $system_project;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $last_health_check_at = null;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: "json")]
    private array $health_check_results = [];

    #[ORM\Column(type: "boolean")]
    private bool $sudo_initialized = false;

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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getDkimPublicKey(): string
    {
        return $this->dkim_public_key;
    }

    public function setDkimPublicKey(string $dkimPublicKey): static
    {
        $this->dkim_public_key = $dkimPublicKey;
        return $this;
    }

    public function getDkimPrivateKeyEncrypted(): string
    {
        return $this->dkim_private_key_encrypted;
    }

    public function setDkimPrivateKeyEncrypted(
        string $dkimPrivateKeyEncrypted
    ): static {
        $this->dkim_private_key_encrypted = $dkimPrivateKeyEncrypted;
        return $this;
    }

    public function getSystemProject(): Project
    {
        return $this->system_project;
    }

    public function setSystemProject(Project $systemProject): static
    {
        $this->system_project = $systemProject;
        return $this;
    }

    public function getLastHealthCheckAt(): ?\DateTimeImmutable
    {
        return $this->last_health_check_at;
    }

    public function setLastHealthCheckAt(?\DateTimeImmutable $lastHealthCheckAt): static
    {
        $this->last_health_check_at = $lastHealthCheckAt;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getHealthCheckResults(): array
    {
        return $this->health_check_results;
    }

    /**
     * @param array<mixed> $healthCheckResults
     */
    public function setHealthCheckResults(array $healthCheckResults): static
    {
        $this->health_check_results = $healthCheckResults;
        return $this;
    }

    public function getSudoInitialized(): bool
    {
        return $this->sudo_initialized;
    }

    public function setSudoInitialized(bool $sudoInitialized): static
    {
        $this->sudo_initialized = $sudoInitialized;
        return $this;
    }

}
