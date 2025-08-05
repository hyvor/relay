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

}
