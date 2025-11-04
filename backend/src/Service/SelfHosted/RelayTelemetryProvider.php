<?php

namespace App\Service\SelfHosted;

use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Server\ServerService;
use Hyvor\Internal\SelfHosted\Provider\TelemetryProviderInterface;

class RelayTelemetryProvider implements TelemetryProviderInterface
{

    private string $instanceUuid;
    private string $version;

    /**
     * @var array<string, mixed>
     */
    private array $payload;


    public function __construct(
        private InstanceService $instanceService,
        private Config $config,
        private ServerService $serverService,
        private IpAddressService $ipAddressService,
    ) {
    }

    public function initialize(): void
    {
        $instance = $this->instanceService->getInstance();
        $this->instanceUuid = $instance->getUuid();
        $this->version = $this->config->getAppVersion();

        $this->payload = [
            'servers_count' => $this->serverService->getServersCount(),
            'ip_addresses_count' => $this->ipAddressService->getIpAddressesCount(),
        ];
    }

    public function getInstanceUuid(): string
    {
        return $this->instanceUuid;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

}