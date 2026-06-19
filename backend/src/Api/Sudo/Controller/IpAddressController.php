<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\UpdateIpAddressInput;
use App\Api\Sudo\Object\IpAddressObject;
use App\Entity\Type\WarmupStatus;
use App\Service\App\Config;
use App\Service\Ip\Dto\UpdateIpAddressDto;
use App\Service\Ip\IpAddressService;
use App\Service\Queue\QueueService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class IpAddressController extends AbstractController
{

    public function __construct(
        private IpAddressService $ipAddressService,
        private QueueService $queueService,
        private Config $appConfig,
    ) {}

    #[Route('/ip-addresses', methods: 'GET')]
    public function getIpAddresses(): JsonResponse
    {
        $ipAddresses = $this->ipAddressService->getAllIpAddresses();

        $ipAddressObjects = array_map(
            fn($ipAddress) => new IpAddressObject($ipAddress, $this->appConfig->getInstanceDomain()),
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

        if ($input->hasProperty('warmup_schedule')) {
            $schedule = $input->warmup_schedule;
            if ($schedule !== null) {
                if (count($schedule) !== 30) {
                    throw new BadRequestHttpException('Warmup schedule must have exactly 30 values.');
                }
                for ($i = 1; $i < 30; $i++) {
                    if ($schedule[$i] < $schedule[$i - 1]) {
                        throw new BadRequestHttpException('Warmup schedule values must not decrease.');
                    }
                }
            }
            $updates->warmup_schedule = $schedule;
        }

        if ($input->hasProperty('warmup_status')) {
            if ($input->warmup_status === null) {
                throw new BadRequestHttpException("Warmup status cannot be null.");
            }
            $updates->warmup_status = WarmupStatus::tryFrom($input->warmup_status);
            if ($updates->warmup_status === null) {
                throw new BadRequestHttpException("Invalid warmup status '{$input->warmup_status}'.");
            }
        }

        $ipAddress = $this->ipAddressService->updateIpAddress($ipAddress, $updates);

        return $this->json(new IpAddressObject($ipAddress, $this->appConfig->getInstanceDomain()));
    }
}
