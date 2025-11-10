<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Instance\InstanceService;
use App\Service\Ip\Dto\PtrValidationDto;

class Ptr
{

    private const string PTR_PREFIX = 'smtp';

    public function __construct(
        private InstanceService $instanceService,
        private DnsResolveInterface $dnsResolver,
    ) {
    }

    /**
     * Forward checks the A record of the PTR domain to see if it points to the IP address.
     */
    private function validateARecord(string $domainToResolve, string $aShouldMatch): PtrValidationDto
    {
        try {
            $dnsAnswer = $this->dnsResolver->resolve($domainToResolve, DnsType::A);
        } catch (DnsResolvingFailedException $e) {
            return new PtrValidationDto(
                valid: false,
                error: 'DNS resolving failed: ' . $e->getMessage()
            );
        }

        if (!$dnsAnswer->ok()) {
            return new PtrValidationDto(
                valid: false,
                error: 'DNS error: ' . $dnsAnswer->error()
            );
        }

        $aRecords = $dnsAnswer->answers;
        // we connect so that it fails if there are multiple A records
        $aRecordsJoined = implode(', ', array_map(fn($answer) => $answer->data, $aRecords));

        if ($aRecordsJoined !== $aShouldMatch) {
            return new PtrValidationDto(
                valid: false,
                error: 'A record mismatch: expected "' . $aShouldMatch . '", got "' . $aRecordsJoined . '"'
            );
        }

        return new PtrValidationDto(valid: true);
    }

    /**
     * @return array{forward: PtrValidationDto, reverse: PtrValidationDto}
     */
    public function validate(IpAddress $ipAddress): array
    {
        $ptrDomain = self::getPtrDomain($ipAddress, $this->instanceService->getInstance()->getDomain());
        $ipString = $ipAddress->getIpAddress();

        $ipSplit = explode('.', $ipString);
        $reverseDomain = implode('.', array_reverse($ipSplit)) . '.in-addr.arpa';

        return [
            'forward' => $this->validateARecord($ptrDomain, $ipString),
            'reverse' => $this->validateARecord($reverseDomain, $ptrDomain)
        ];
    }

    public static function getPtrDomain(IpAddress $ipAddress, string $instanceDomain): string
    {
        return self::PTR_PREFIX . $ipAddress->getId() . '.' . $instanceDomain;
    }

}