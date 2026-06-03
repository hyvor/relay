<?php

namespace App\Entity;

use App\Entity\Type\WarmupStatus;
use App\Repository\IpAddressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IpAddressRepository::class)]
#[ORM\Table(name: 'ip_addresses')]
#[ORM\HasLifecycleCallbacks]
class IpAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn()]
    private Server $server;

    #[ORM\Column(type: 'string', length: 45)]
    private string $ip_address;

    #[ORM\ManyToOne(targetEntity: Queue::class)]
    #[ORM\JoinColumn()]
    private ?Queue $queue;

    #[ORM\Column(type: 'boolean')]
    private bool $is_ptr_forward_valid = true;

    #[ORM\Column(type: 'boolean')]
    private bool $is_ptr_reverse_valid = true;

    #[ORM\Column(type: 'string', enumType: WarmupStatus::class)]
    private WarmupStatus $warmup_status = WarmupStatus::WARMING;

    #[ORM\Column(type: 'date', nullable: true)]
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

    public function __construct()
    {
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

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function setServer(Server $server): static
    {
        $this->server = $server;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ip_address;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ip_address = $ipAddress;
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

    public function getIsPtrForwardValid(): bool
    {
        return $this->is_ptr_forward_valid;
    }

    public function setIsPtrForwardValid(bool $isPtrForwardValid): static
    {
        $this->is_ptr_forward_valid = $isPtrForwardValid;
        return $this;
    }

    public function getIsPtrReverseValid(): bool
    {
        return $this->is_ptr_reverse_valid;
    }

    public function setIsPtrReverseValid(bool $isPtrReverseValid): static
    {
        $this->is_ptr_reverse_valid = $isPtrReverseValid;
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