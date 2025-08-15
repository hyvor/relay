<?php

namespace App\Entity;

use App\Entity\Type\ServerTaskType;
use App\Repository\ServerTaskRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerTaskRepository::class)]
#[ORM\Table(name: 'server_tasks')]
class ServerTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn]
    private Server $server;

    #[ORM\Column(enumType: ServerTaskType::class)]
    private ServerTaskType $type;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: "json")]
    private array $payload;

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

    public function getServer(): Server
    {
        return $this->server;
    }

    public function setServer(Server $server): self
    {
        $this->server = $server;
        return $this;
    }

    public function getType(): ServerTaskType
    {
        return $this->type;
    }

    public function setType(ServerTaskType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

}
