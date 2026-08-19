<?php

namespace App\Entity;

use App\Entity\Type\WarmupStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'warmup_schedules')]
class WarmupSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: IpAddress::class, inversedBy: 'warmupSchedules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IpAddress $ip_address;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column(type: 'string', enumType: WarmupStatus::class)]
    private WarmupStatus $status = WarmupStatus::WARMING;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $started_date;

    #[ORM\Column(type: 'integer')]
    private int $sent_today;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $max_today = 0;

    /**
     * @var array<int>
     */
    #[ORM\Column(type: 'json')]
    private array $schedule;

    /**
     * @var array<int>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $results = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getIpAddress(): IpAddress
    {
        return $this->ip_address;
    }

    public function setIpAddress(IpAddress $ipAddress): static
    {
        $this->ip_address = $ipAddress;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updated_at = $updatedAt;
        return $this;
    }

    public function getStatus(): WarmupStatus
    {
        return $this->status;
    }

    public function setStatus(WarmupStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartedDate(): \DateTimeImmutable
    {
        return $this->started_date;
    }

    public function setStartedDate(\DateTimeImmutable $startedDate): static
    {
        $this->started_date = $startedDate;
        return $this;
    }

    public function getSentToday(): int
    {
        return $this->sent_today;
    }

    public function setSentToday(int $sentToday): static
    {
        $this->sent_today = $sentToday;
        return $this;
    }

    public function getMaxToday(): int
    {
        return $this->max_today;
    }

    public function setMaxToday(int $maxToday): static
    {
        $this->max_today = $maxToday;
        return $this;
    }

    /**
     * @return array<int>
     */
    public function getSchedule(): array
    {
        return $this->schedule;
    }

    /**
     * @param array<int> $schedule
     */
    public function setSchedule(array $schedule): static
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * @return array<int>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param array<int> $results
     */
    public function setResults(array $results): static
    {
        $this->results = $results;
        return $this;
    }

    public function appendResult(int $value): static
    {
        $this->results[] = $value;
        return $this;
    }
}
