<?php

namespace App\Service\Management\Health;

use App\Service\App\Config;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Instance\InstanceService;
use App\Service\Management\Health\Event\DnsServerCorrectlyPointedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Checks if the DNS server is correctly pointed by verifying a static DNS records:
 * TXT record for "_instance.<instance_domain>" with value of instance UUID.
 *
 * Note: it is possible a different DNS server handles this, but the chance is low.
 * We do not check the IP address directly because that will break if a reverse proxy is used.
 */
class DnsServerPointedHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private InstanceService $instanceService,
        private Config $config,
        private DnsResolveInterface $dnsResolve,
        private EventDispatcherInterface $ed,
    ) {
    }

    public function check(): bool
    {
        $instance = $this->instanceService->getInstance();

        try {
            $dnsAnswer = $this->dnsResolve->resolve(
                "_hash." . $this->config->getInstanceDomain(),
                DnsType::TXT
            );
        } catch (DnsResolvingFailedException $e) {
            $this->setData([
                'error' => 'DNS resolving failed: ' . $e->getMessage(),
            ]);
            return false;
        }

        if (!$dnsAnswer->ok()) {
            $this->setData([
                'error' => 'DNS query was not successful: ' . $dnsAnswer->error(),
            ]);
            return false;
        }

        $txtRecord = $dnsAnswer->answers[0] ?? null;

        if ($txtRecord === null) {
            $this->setData([
                'error' => 'The required TXT record was not found.',
            ]);
            return false;
        }

        $txtValue = $txtRecord->getCleanedTxt();
        $expectedValue = hash('sha256', $instance->getUuid());
        if ($txtValue !== $expectedValue) {
            $this->setData([
                'error' => "The TXT record content does not match the instance UUID. Expected {$expectedValue}, found {$txtValue}.",
            ]);
            return false;
        }

        $this->ed->dispatch(new DnsServerCorrectlyPointedEvent());

        return true;
    }
}