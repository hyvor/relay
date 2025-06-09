<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class Config
{

    public function __construct(
        #[Autowire('%env(string:HOSTNAME)%')]
        private string $hostname,

        #[Autowire('%env(bool:API_ON)%')]
        private bool $apiOn,

        #[Autowire('%env(bool:EMAIL_ON)%')]
        private bool $emailOn,

        #[Autowire('%env(bool:WEBHOOK_ON)%')]
        private bool $webhookOn,
    )
    {
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function isApiOn(): bool
    {
        return $this->apiOn;
    }

    public function isEmailOn(): bool
    {
        return $this->emailOn;
    }

    public function isWebhookOn(): bool
    {
        return $this->webhookOn;
    }

}