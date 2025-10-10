<?php

namespace App\Entity;

use App\Entity\Type\WebhookDeliveryStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'webhook_deliveries')]
class WebhookDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private \DateTimeImmutable $created_at;

    #[ORM\Column]
    private \DateTimeImmutable $updated_at;

    #[ORM\Column]
    private \DateTimeImmutable $send_after;

    #[ORM\ManyToOne(targetEntity: Webhook::class)]
    #[ORM\JoinColumn]
    private Webhook $webhook;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column(enumType: WebhooksEventEnum::class)]
    private WebhooksEventEnum $event;

    #[ORM\Column(enumType: WebhookDeliveryStatus::class)]
    private WebhookDeliveryStatus $status ;

    #[ORM\Column()]
    private string $request_body;

    #[ORM\Column()]
    private ?string $response;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $response_code = null;

    #[ORM\Column]
    private int $try_count = 0;

    #[ORM\Column]
    private ?string $signature = null;

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

    public function getSendAfter(): \DateTimeImmutable
    {
        return $this->send_after;
    }

    public function setSendAfter(\DateTimeImmutable $send_after): static
    {
        $this->send_after = $send_after;

        return $this;
    }

    public function getWebhook(): Webhook
    {
        return $this->webhook;
    }

    public function setWebhook(Webhook $webhook): static
    {
        $this->webhook = $webhook;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getEvent(): WebhooksEventEnum
    {
        return $this->event;
    }

    public function setEvent(WebhooksEventEnum $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getStatus(): WebhookDeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(WebhookDeliveryStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestBody(): string
    {
        return $this->request_body;
    }

    public function setRequestBody(string $request_body): static
    {
        $this->request_body = $request_body;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->response_code;
    }

    public function setResponseCode(?int $responseCode): static
    {
        $this->response_code = $responseCode;

        return $this;
    }

    public function getTryCount(): int
    {
        return $this->try_count;
    }

    public function setTryCount(int $tryCount): static
    {
        $this->try_count = $tryCount;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): static
    {
        $this->signature = $signature;

        return $this;
    }
}
