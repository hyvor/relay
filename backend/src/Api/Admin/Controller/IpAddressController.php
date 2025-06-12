<?php

namespace App\Api\Admin\Controller;

use App\Api\Admin\Object\IpAddressObject;
use App\Service\Ip\IpAddressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class IpAddressController extends AbstractController
{

    public function __construct(
        private IpAddressService $ipAddressService
    )
    {
    }

    #[Route('/ip-addresses', methods: 'GET')]
    public function getIpAddresses(): JsonResponse
    {
        $ipAddresses = $this->ipAddressService->getAllIpAddresses();
        
        $ipAddressObjects = array_map(
            fn($ipAddress) => new IpAddressObject($ipAddress),
            $ipAddresses
        );

        return $this->json($ipAddressObjects);
    }

}