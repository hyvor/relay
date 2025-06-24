<?php

namespace App\Service\Webhook;

use App\Entity\Project;
use App\Entity\Webhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class WebhookService
{
    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    )
    {}

    public function createWebhook(Project $project, string $url, string $description): Webhook
    {
        $webhook = new Webhook();
        $webhook->setProject($project);
        $webhook->setUrl($url);
        $webhook->setDescription($description);
        $webhook->setCreatedAt($this->clock?->now());
        $webhook->setUpdatedAt($this->clock?->now());

        $this->em->persist($webhook);
        $this->em->flush();

        return $webhook;
    }

    public function getWebhooksForProject(Project $project): array
    {
        return $this->em->getRepository(Webhook::class)->findBy(['project' => $project]);
    }

    public function deleteWebhook(Webhook $webhook): void
    {
        $this->em->remove($webhook);
        $this->em->flush();
    }
}