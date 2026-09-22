<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\CreateWarmupScheduleInput;
use App\Api\Sudo\Input\UpdateWarmupScheduleInput;
use App\Api\Sudo\Object\WarmupScheduleObject;
use App\Service\Ip\Dto\UpdateWarmupScheduleDto;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\WarmupScheduleService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class WarmupScheduleController extends AbstractController
{

    public function __construct(
        private WarmupScheduleService $warmupScheduleService,
        private IpAddressService $ipAddressService,
    ) {}

    #[Route('/warmup-schedules', methods: 'GET')]
    public function getWarmupSchedules(Request $request): JsonResponse
    {
        $ipAddressId = $request->query->has('ip_address_id')
            ? $request->query->getInt('ip_address_id')
            : null;

        $schedules = $this->warmupScheduleService->getWarmupSchedules($ipAddressId);

        $scheduleObjects = array_map(
            fn($schedule) => new WarmupScheduleObject($schedule),
            $schedules
        );

        return $this->json($scheduleObjects);
    }

    #[Route('/warmup-schedules', methods: 'POST')]
    public function createWarmupSchedule(#[MapRequestPayload] CreateWarmupScheduleInput $input): JsonResponse
    {
        $ipAddress = $this->ipAddressService->getIpAddressById($input->ip_address_id);

        if (!$ipAddress) {
            throw new BadRequestHttpException("IP address with ID '{$input->ip_address_id}' does not exist.");
        }

        $warmup = $this->warmupScheduleService->createWarmupSchedule(
            $ipAddress,
            $input->schedule,
        );

        return $this->json(new WarmupScheduleObject($warmup));
    }

    #[Route('/warmup-schedules/{id}', methods: 'PATCH')]
    public function updateWarmupSchedule(int $id, #[MapRequestPayload] UpdateWarmupScheduleInput $input): JsonResponse
    {
        $warmup = $this->warmupScheduleService->getWarmupScheduleById($id);

        if (!$warmup) {
            throw new BadRequestHttpException("Warmup schedule with ID '$id' does not exist.");
        }

        $updates = new UpdateWarmupScheduleDto();
        if ($input->hasProperty('schedule')) {
            $updates->schedule = $input->schedule;
        }
        if ($input->hasProperty('status')) {
            $updates->status = $input->status;
        }

        $warmup = $this->warmupScheduleService->updateWarmupSchedule($warmup, $updates);

        return $this->json(new WarmupScheduleObject($warmup));
    }

    #[Route('/warmup-schedules/{id}', methods: 'DELETE')]
    public function deleteWarmupSchedule(int $id): JsonResponse
    {
        $warmup = $this->warmupScheduleService->getWarmupScheduleById($id);

        if (!$warmup) {
            throw new BadRequestHttpException("Warmup schedule with ID '$id' does not exist.");
        }

        $this->warmupScheduleService->deleteWarmupSchedule($warmup);

        return $this->json(['success' => true]);
    }
}
