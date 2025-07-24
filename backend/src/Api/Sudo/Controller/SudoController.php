<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\InstanceObject;
use App\Config;
use App\Service\Instance\InstanceService;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SudoController extends AbstractController
{

    public function __construct(
        private Config $config,
        private InternalConfig $internalConfig,
        private InstanceService $instanceService
    )
    {
    }

    #[Route('/init', methods: 'POST')]
    public function initSudo(): JsonResponse
    {

        $instance = $this->instanceService->getInstance();

        return new JsonResponse([
            'config' => [
                'app_version' => $this->config->getAppVersion(),
                'instance' => $this->internalConfig->getInstance()
            ],
            'instance' => new InstanceObject($instance)
        ]);

    }

}