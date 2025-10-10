<?php

namespace App\Service\Webhook;

use App\Entity\Project;
use App\Entity\Type\WebhookDeliveryStatus;
use App\Entity\Type\WebhooksEventEnum;
use App\Entity\Webhook;
use App\Entity\WebhookDelivery;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use App\Service\Webhook\Dto\UpdateWebhookDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Hyvor\Internal\Util\Crypt\Encryption;
use Symfony\Component\Clock\ClockAwareTrait;

class WebhookService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private Encryption $encryption,
    ) {}

    /**
     * @param array<string> $events
     * @return array{ webhook: Webhook, rawKey: string }
     */
    public function createWebhook(
        Project $project,
        string $url,
        string $description,
        array $events
    ): array {
        $key = bin2hex(random_bytes(16));
        $webhook = new Webhook();
        $webhook->setProject($project);
        $webhook->setUrl($url);
        $webhook->setDescription($description);
        $webhook->setEvents($events);
        $webhook->setSecretEncrypted($this->encryption->encryptString($key));
        $webhook->setCreatedAt($this->now());
        $webhook->setUpdatedAt($this->now());

        $this->em->persist($webhook);
        $this->em->flush();

        return [
            'webhook' => $webhook,
            'rawKey' => $key,
        ];
    }

    /**
     * @return ArrayCollection<int, Webhook>
     */
    public function getWebhooksForProject(Project $project): ArrayCollection
    {
        $webhooks = $this->em
            ->getRepository(Webhook::class)
            ->findBy(["project" => $project]);
        return new ArrayCollection($webhooks);
    }

    /**
     * @return Webhook[]
     */
    public function getWebhooksForEvent(
        Project $project,
        WebhooksEventEnum $event
    ): array {

        $sql = "SELECT w.* FROM webhooks w
                WHERE w.project_id = :project_id
                AND w.events @> :event
                ORDER BY w.id ASC";

        $rsm = new ResultSetMappingBuilder($this->em);
        $rsm->addRootEntityFromClassMetadata(Webhook::class, "w");

        $query = $this->em->createNativeQuery($sql, $rsm);
        $query->setParameter("project_id", $project->getId());
        $query->setParameter("event", (string) json_encode([$event->value]));

        /** @var Webhook[] $webhooks */
        $webhooks = $query->getResult();

        return $webhooks;
    }

    public function deleteWebhook(Webhook $webhook): void
    {
        $this->em->remove($webhook);
        $this->em->flush();
    }

    public function updateWebhook(
        Webhook $webhook,
        UpdateWebhookDto $updates
    ): Webhook {
        if ($updates->hasProperty("url")) {
            $webhook->setUrl($updates->url);
        }

        if ($updates->hasProperty("description")) {
            $webhook->setDescription($updates->description);
        }

        if ($updates->hasProperty("events")) {
            $webhook->setEvents($updates->events);
        }

        $webhook->setUpdatedAt($this->now());
        $this->em->persist($webhook);
        $this->em->flush();

        return $webhook;
    }

    public function createWebhookDelivery(
        Webhook $webhook,
        WebhooksEventEnum $eventType,
        object $payload
    ): WebhookDelivery {
        $requestBody = [
            'event' => $eventType->value,
            'payload' => $payload,
        ];
        $requestBody = (string) json_encode($requestBody);

        $delivery = new WebhookDelivery();
        $delivery->setCreatedAt($this->now());
        $delivery->setUpdatedAt($this->now());
        $delivery->setSendAfter($this->now());
        $delivery->setWebhook($webhook);
        $delivery->setUrl($webhook->getUrl());
        $delivery->setStatus(WebhookDeliveryStatus::PENDING);
        $delivery->setEvent($eventType);
        $delivery->setRequestBody($requestBody);
        $delivery->setSignature(hash_hmac(
            'sha256',
            $requestBody,
            $this->encryption->decryptString($webhook->getSecretEncrypted())
        ));

        $this->em->persist($delivery);
        $this->em->flush();

        return $delivery;
    }
}
