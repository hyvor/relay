<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Input\UpdateInstanceInput;
use App\Api\Sudo\Object\InstanceObject;
use App\Service\Instance\Dto\UpdateInstanceDto;
use App\Service\Instance\InstanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class InstanceController extends AbstractController
{

    public function __construct(
        private InstanceService $instanceService,
    )
    {
    }

    #[Route('/instance', methods: 'PATCH')]
    public function updateInstance(
        #[MapRequestPayload] UpdateInstanceInput $input
    ): JsonResponse
    {

        $instance = $this->instanceService->getInstance();

        $updates = new UpdateInstanceDto();
        if ($input->domainSet) {
            $updates->domain = $input->domain;
        }

        $this->instanceService->updateInstance($instance, $updates);

        return new JsonResponse(
            new InstanceObject($instance)
        );

    }

}