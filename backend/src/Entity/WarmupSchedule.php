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

    #[ORM\ManyToOne(targetEntity: IpAddress::class)]
    #[ORM\JoinColumn(nullable: false)]
    private IpAddress $ip_address;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'string', enumType: WarmupStatus::class)]
    private WarmupStatus $warmup_status = WarmupStatus::WARMING;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $warmup_started_date = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $warmup_sent_today = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $warmup_max_today = 0;

    /**
     * @var array<int>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $warmup_schedule = null;

    public function __construct(IpAddress $ipAddress)
    {
        $this->ip_address = $ipAddress;
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

    public function getIpAddress(): IpAddress
    {
        return $this->ip_address;
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

    public function getWarmupStatus(): WarmupStatus
    {
        return $this->warmup_status;
    }

    public function setWarmupStatus(WarmupStatus $warmupStatus): static
    {
        $this->warmup_status = $warmupStatus;
        return $this;
    }

    public function getWarmupStartedDate(): ?\DateTimeImmutable
    {
        return $this->warmup_started_date;
    }

    public function setWarmupStartedDate(?\DateTimeImmutable $warmupStartedDate): static
    {
        $this->warmup_started_date = $warmupStartedDate;
        return $this;
    }

    public function getWarmupSentToday(): int
    {
        return $this->warmup_sent_today;
    }

    public function setWarmupSentToday(int $warmupSentToday): static
    {
        $this->warmup_sent_today = $warmupSentToday;
        return $this;
    }

    public function getWarmupMaxToday(): int
    {
        return $this->warmup_max_today;
    }

    public function setWarmupMaxToday(int $warmupMaxToday): static
    {
        $this->warmup_max_today = $warmupMaxToday;
        return $this;
    }

    /**
     * @return array<int>|null
     */
    public function getWarmupSchedule(): ?array
    {
        return $this->warmup_schedule;
    }

    /**
     * @param array<int>|null $warmupSchedule
     */
    public function setWarmupSchedule(?array $warmupSchedule): static
    {
        $this->warmup_schedule = $warmupSchedule;
        return $this;
    }

    public function isWarmingUp(): bool
    {
        return $this->warmup_status === WarmupStatus::WARMING
            && $this->warmup_started_date !== null
            && $this->warmup_schedule !== null;
    }
}
