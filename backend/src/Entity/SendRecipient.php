<?php

namespace App\Entity;

use App\Entity\Type\SendRecipientType;
use App\Repository\SendRecipientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SendRecipientRepository::class)]
#[ORM\Table(name: "send_recipients")]
class SendRecipient
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Send::class)]
    #[ORM\JoinColumn]
    private Send $send;

    #[ORM\Column(type: "string")]
    private string $address;

    #[ORM\Column(type: "string")]
    private string $name;

    #[ORM\Column(type: "string", enumType: SendRecipientType::class)]
    private SendRecipientType $type;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getSend(): Send
    {
        return $this->send;
    }

    public function setSend(Send $send): static
    {
        $this->send = $send;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): SendRecipientType
    {
        return $this->type;
    }

    public function setType(SendRecipientType $type): static
    {
        $this->type = $type;
        return $this;
    }

}