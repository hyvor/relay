<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\UpdateIpAddressInput;
use App\Api\Sudo\Object\IpAddressObject;
use App\Entity\IpAddress;
use App\Service\Instance\InstanceService;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\IpAddressService;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class IpAddressController extends AbstractController
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private InstanceService $instanceService,
        private QueueService $queueService
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

    #[Route('/ip-addresses/{id}', methods: 'PATCH')]
    public function updateIpAddress(int $id, #[MapRequestPayload] UpdateIpAddressInput $input): JsonResponse
    {
        $ipAddress = $this->ipAddressService->getIpAddressById($id);

        if (!$ipAddress) {
            throw new BadRequestHttpException("IP address with ID '$id' does not exist.");
        }

        $updates = new UpdateIpAddressDto();
        if ($input->hasProperty('is_active')) {
            $updates->isActive = $input->is_active;
        }
        if ($input->hasProperty('queue_id')) {
            if ($input->queue_id === null) {
                $updates->queue = null;
            } else {
                $queue = $this->queueService->getQueueById($input->queue_id);
                if (!$queue) {
                    throw new BadRequestHttpException("Queue with ID '{$input->queue_id}' does not exist.");
                }
                $updates->queue = $queue;
            }
        }

        $ipAddress = $this->ipAddressService->updateIpAddress($ipAddress, $updates);
        $instance = $this->instanceService->getInstance();

        return $this->json(new IpAddressObject($ipAddress, $instance));
    }
}
