<?php

namespace App\Service\Webhook;

use App\Entity\Project;
use App\Entity\Webhook;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use App\Service\Webhook\Dto\UpdateWebhookDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class WebhookService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    )
    {}

    /**
     * @param array<string> $events
     */
    public function createWebhook(Project $project, string $url, string $description, array $events): Webhook
    {
        $webhook = new Webhook();
        $webhook->setProject($project);
        $webhook->setUrl($url);
        $webhook->setDescription($description);
        $webhook->setEvents($events);
        $webhook->setCreatedAt($this->now());
        $webhook->setUpdatedAt($this->now());

        $this->em->persist($webhook);
        $this->em->flush();

        return $webhook;
    }

    /**
     * @return ArrayCollection<int, Webhook>
     */
    public function getWebhooksForProject(Project $project): ArrayCollection
    {
        $webhooks =  $this->em->getRepository(Webhook::class)->findBy(['project' => $project]);
        return new ArrayCollection($webhooks);
    }

    public function deleteWebhook(Webhook $webhook): void
    {
        $this->em->remove($webhook);
        $this->em->flush();
    }

    public function updateWebhook(Webhook $webhook, UpdateWebhookDto $updates): Webhook
    {
        if ($updates->hasProperty('url')) {
            $webhook->setUrl($updates->url);
        }

        if ($updates->hasProperty('description')) {
            $webhook->setDescription($updates->description);
        }

        if ($updates->hasProperty('events')) {
            $webhook->setEvents($updates->events);
        }

        $webhook->setUpdatedAt($this->now());
        $this->em->persist($webhook);
        $this->em->flush();

        return $webhook;
    }
}