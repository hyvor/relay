<?php

namespace App\Service\SelfHosted;

use App\Service\App\Config;
use App\Service\Domain\DomainService;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Project\ProjectService;
use App\Service\Queue\QueueService;
use App\Service\Send\SendService;
use App\Service\Server\ServerService;
use App\Service\Webhook\WebhookDeliveryService;
use Hyvor\Internal\Auth\Oidc\OidcUserService;
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
        private OidcUserService $oidcUserService,
        private ProjectService $projectService,
        private DomainService $domainService,
        private SendService $sendService,
        private WebhookDeliveryService $webhookDeliveryService,
        private QueueService $queueService,
    ) {
    }

    public function initialize(): void
    {
        $instance = $this->instanceService->getInstance();
        $this->instanceUuid = $instance->getUuid();
        $this->version = $this->config->getAppVersion();

        $workersCounts = $this->serverService->getAllWorkersCounts();
        $domainCounts = $this->domainService->getDomainsCounts();
        $sendCounts = $this->sendService->getLast24HoursSendCount();

        $this->payload = [
            'servers_count' => $this->serverService->getServersCount(),
            'ip_addresses_count' => $this->ipAddressService->getIpAddressesCount(),
            'queues_count' => $this->queueService->getQueuesCount(),
            'workers_api_count' => $workersCounts['api_workers'],
            'workers_email_count' => $workersCounts['email_workers'],
            'workers_incoming_count' => $workersCounts['incoming_workers'],
            'workers_webhook_count' => $workersCounts['webhook_workers'],
            'users_count' => $this->oidcUserService->getTotalUserCount(),
            'projects_count' => $this->projectService->getTotalProjectsCount(),
            'domains_count' => $domainCounts['total'],
            'domains_active_count' => $domainCounts['active'],
            'sends_24h_count' => $sendCounts['sends_24h_count'],
            'recipients_24h_count' => $sendCounts['recipients_24h_count'],
            'recipients_24h_accepted_count' => $sendCounts['recipients_24h_accepted_count'],
            'recipients_24h_bounced_count' => $sendCounts['recipients_24h_bounced_count'],
            'recipients_24h_complained_count' => $sendCounts['recipients_24h_complained_count'],
            'recipients_24h_failed_count' => $sendCounts['recipients_24h_failed_count'],
            'recipients_24h_suppressed_count' => $sendCounts['recipients_24h_suppressed_count'],
            'webhooks_24h_deliveries_count' => $this->webhookDeliveryService->getLast24HoursDeliveriesCount(),
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