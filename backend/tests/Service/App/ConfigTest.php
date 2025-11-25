<?php

namespace App\Tests\Service\App;

use App\Service\App\Config;
use App\Service\App\HostingEnum;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Config::class)]
class ConfigTest extends KernelTestCase
{

    public function test_config(): void
    {
        $config = $this->getService(Config::class);

        $this->assertSame('0.0.0', $config->getAppVersion());
        $this->assertSame('hyvor-relay', $config->getHostname());
        $this->assertSame('test', $config->getEnv());
        $this->assertSame(HostingEnum::CLOUD, $config->getHosting());
        $this->assertSame(null, $config->getGoHost());
        $this->assertSame("https://relay.hyvor.com", $config->getWebUrl());
        $this->assertSame("mail.hyvor-relay.com", $config->getInstanceDomain());
    }

    public function test_get_hostname(): void
    {
        $this->setConfig('envHostname', '');

        $config = $this->getService(Config::class);
        $hostname = $config->getHostname();
        $this->assertNotEmpty($hostname);
    }

}