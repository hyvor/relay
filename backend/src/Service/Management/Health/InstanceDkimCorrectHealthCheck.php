<?php

namespace App\Service\Management\Health;

use App\Service\Domain\Dkim;
use App\Service\Instance\InstanceService;

class InstanceDkimCorrectHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private InstanceService $instanceService,
        /** @var callable(string, int): mixed */
        private $dnsGetRecord = 'dns_get_record'
    )
    {
    }

    public function check(): bool
    {
        $instance = $this->instanceService->getInstance();
        $dkimHost = Dkim::dkimHost(InstanceService::DEFAULT_DKIM_SELECTOR, $instance->getDomain());
        $expectedDkimTxtValue = Dkim::dkimTxtValue($instance->getDkimPublicKey());

        /** @var false|array<array{txt?: string}> $result */
        $result = call_user_func($this->dnsGetRecord, $dkimHost, DNS_TXT);

        if ($result === false) {
            $this->setData([
                'error' => 'DNS lookup failed for DKIM record for ' . $dkimHost
            ]);
            return false;
        }

        if (count($result) === 0) {
            $this->setData([
                'error' => 'No DKIM record found for ' . $dkimHost
            ]);
            return false;
        }

        $dkimRecord = $result[0]['txt'] ?? null;

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