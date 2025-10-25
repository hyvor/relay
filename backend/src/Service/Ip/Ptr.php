<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Service\Instance\InstanceService;

// This currently uses PHP gethostbyname and gethostbyaddr functions.
// In future, we might want to replace it with DnsResolveInterface with proper error handling.
class Ptr
{

    private const string PTR_PREFIX = 'smtp';

    public function __construct(
        private InstanceService $instanceService,
        /** @var callable */
        private $gethostbynameFunction = 'gethostbyname',
        /** @var callable */
        private $gethostbyaddrFunction = 'gethostbyaddr'
    ) {
    }

    /**
     * Forward checks the A record of the PTR domain to see if it points to the IP address.
     */
    private function validateForward(IpAddress $ipAddress): bool
    {
        $ptrDomain = self::getPtrDomain($ipAddress, $this->instanceService->getInstance()->getDomain());
        $aRecord = call_user_func(
            $this->gethostbynameFunction,
            $ptrDomain
        );
        return $aRecord === $ipAddress->getIpAddress();
    }

    /**
     * Reverse checks the PTR record of the IP address to see if it points to the PTR domain.
     */
    private function validateReverse(IpAddress $ipAddress): bool
    {
        $ptrDomain = self::getPtrDomain($ipAddress, $this->instanceService->getInstance()->getDomain());
        $reverseLookup = call_user_func(
            $this->gethostbyaddrFunction,
            $ipAddress->getIpAddress()
        );
        return $reverseLookup === $ptrDomain;
    }

    /**
     * @return array{forward: bool, reverse: bool}
     */
    public function validate(IpAddress $ipAddress): array
    {
        return [
            'forward' => $this->validateForward($ipAddress),
            'reverse' => $this->validateReverse($ipAddress),
        ];
    }

    public static function getPtrDomain(IpAddress $ipAddress, string $instanceDomain): string
    {
        return self::PTR_PREFIX . $ipAddress->getId() . '.' . $instanceDomain;
    }

}