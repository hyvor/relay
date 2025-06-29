<?php

namespace App;

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
        private string $hostname,
    )
    {
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

}