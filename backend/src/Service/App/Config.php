<?php

namespace App\Service\App;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @readonly
 */
class Config
{

    public function __construct(
        #[Autowire('%env(string:APP_VERSION)%')]
        private string $appVersion,
        #[Autowire('%env(string:HOST_HOSTNAME)%')]
        private string $envHostname,
        #[Autowire('%kernel.environment%')]
        private string $env,
        #[Autowire('%env(string:HOSTING)%')]
        private string $hosting,
        #[Autowire('%env(string:WEB_URL)%')]
        private string $webUrl,
        #[Autowire('%env(string:INSTANCE_DOMAIN)%')]
        private string $instanceDomain,

        // usually only needed in DEV where Go is not running on localhost
        #[Autowire('%env(GO_HOST)%')]
        private ?string $goHost = null,

        // SECURITY SETTINGS ============
        /**
         * Comma-separated list of allowed source IPs/CIDRs for incoming SMTP.
         */
        #[Autowire('%env(string:ALLOWED_SOURCE_IPS)%')]
        private string $allowedSourceIps = '',

        /**
         * Comma-separated list of allowed sender domains for incoming SMTP.
         */
        #[Autowire('%env(string:ALLOWED_SENDER_DOMAINS)%')]
        private string $allowedSenderDomains = '',

        /**
         * Whether to delegate SMTP AUTH to Symfony backend.
         */
        #[Autowire('%env(bool:SMTP_AUTH_VIA_SYMFONY)%')]
        private bool $smtpAuthViaSymfony = false,

        /**
         * Whether to allow sending via incoming SMTP without authentication.
         * When enabled, the UNAUTHENTICATED_SEND_API_KEY env var must be set on the Go worker
         * with a valid API key to use for submissions.
         */
        #[Autowire('%env(bool:ALLOW_UNAUTHENTICATED_SENDING)%')]
        private bool $allowUnauthenticatedSending = false,
    ) {
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    public function getHostname(): string
    {
        if (!empty($this->envHostname)) {
            return $this->envHostname;
        } else {
            $hostname = gethostname();
            assert(is_string($hostname), 'Hostname must be a string');
            return $hostname;
        }
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function getHosting(): HostingEnum
    {
        return HostingEnum::tryFrom($this->hosting) ?? HostingEnum::SELF;
    }

    public function getWebUrl(): string
    {
        return $this->webUrl;
    }

    public function getInstanceDomain(): string
    {
        return $this->instanceDomain;
    }

    public function getGoHost(): ?string
    {
        return $this->goHost;
    }

    /**
     * @return string[]
     */
    public function getAllowedSourceIps(): array
    {
        if (empty($this->allowedSourceIps)) {
            return [];
        }
        return array_map('trim', explode(',', $this->allowedSourceIps));
    }

    /**
     * @return string[]
     */
    public function getAllowedSenderDomains(): array
    {
        if (empty($this->allowedSenderDomains)) {
            return [];
        }
        return array_map('trim', explode(',', $this->allowedSenderDomains));
    }

    public function getSmtpAuthViaSymfony(): bool
    {
        return $this->smtpAuthViaSymfony;
    }

    public function getAllowUnauthenticatedSending(): bool
    {
        return $this->allowUnauthenticatedSending;
    }

}
