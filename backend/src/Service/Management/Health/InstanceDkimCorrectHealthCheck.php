<?php

namespace App\Service\Management\Health;

use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Domain\Dkim;
use App\Service\Instance\InstanceService;

class InstanceDkimCorrectHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private InstanceService $instanceService,
        private DnsResolveInterface $dnsResolve,
    ) {
    }

    public function check(): bool
    {
        $instance = $this->instanceService->getInstance();
        $dkimHost = Dkim::dkimHost(InstanceService::DEFAULT_DKIM_SELECTOR, $instance->getDomain());
        $expectedDkimTxtValue = Dkim::dkimTxtValue($instance->getDkimPublicKey());

        try {
            $result = $this->dnsResolve->resolve($dkimHost, DnsType::TXT);
        } catch (DnsResolvingFailedException $e) {
            $this->setData([
                'error' => "DNS resolving failed for $dkimHost: " . $e->getMessage()
            ]);
            return false;
        }

        if ($result->ok() === false) {
            $this->setData([
                'error' => "DNS query for $dkimHost failed with error: " . $result->error()
            ]);
            return false;
        }

        if (count($result->answers) === 0) {
            $this->setData([
                'error' => 'No DKIM record found for ' . $dkimHost
            ]);
            return false;
        }

        $dkimRecord = $result->answers[0]->getCleanedTxt();

        if ($dkimRecord !== $expectedDkimTxtValue) {
            $this->setData([
                'error' => 'DKIM record does not match expected value',
                'expected' => $expectedDkimTxtValue,
                'actual' => $dkimRecord
            ]);
            return false;
        }

        return true;
    }

}