<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\IpAddressObject;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class IpAddressController extends AbstractController
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private InstanceService $instanceService
    )
    {
    }

    #[Route('/ip-addresses', methods: 'GET')]
    public function getIpAddresses(): JsonResponse
    {
        $ipAddresses = $this->ipAddressService->getAllIpAddresses();
        $instance = $this->instanceService->getInstance();
        
        $ipAddressObjects = array_map(
            fn($ipAddress) => new IpAddressObject($ipAddress, $instance),
            $ipAddresses
        );

        return $this->json($ipAddressObjects);
    }

}