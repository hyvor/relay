<?php

namespace App\Entity;

use App\Entity\Type\ProviderMetricSource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'provider_metrics')]
class ProviderMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'string', enumType: ProviderMetricSource::class)]
    private ProviderMetricSource $source;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: IpAddress::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?IpAddress $ip_address = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $metric_date;

    #[ORM\Column(type: 'decimal')]
    private string $value;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processed_at = null;

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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->created_at = $createdAt;
        return $this;
    }

    public function getSource(): ProviderMetricSource
    {
        return $this->source;
    }

    public function setSource(ProviderMetricSource $source): static
    {
        $this->source = $source;
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

    public function getIpAddress(): ?IpAddress
    {
        return $this->ip_address;
    }

    public function setIpAddress(?IpAddress $ipAddress): static
    {
        $this->ip_address = $ipAddress;
        return $this;
    }

    public function getMetricDate(): \DateTimeImmutable
    {
        return $this->metric_date;
    }

    public function setMetricDate(\DateTimeImmutable $metricDate): static
    {
        $this->metric_date = $metricDate;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
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
}
