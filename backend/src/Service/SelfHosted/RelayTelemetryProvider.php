<?php

namespace App\Service\SelfHosted;

use App\Service\App\Config;
use App\Service\Instance\InstanceService;
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
    ) {
    }

    public function record(): void
    {
        $instance = $this->instanceService->getInstance();
        $this->instanceUuid = $instance->getUuid();
        $this->version = $this->config->getAppVersion();

        $this->payload = [
            'relay' => true,
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