<?php

namespace App\Api\Sudo\Controller;

use App\Config;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SudoController extends AbstractController
{

    public function __construct(
        private Config $config,
        private InternalConfig $internalConfig
    )
    {
    }

    #[Route('/init', methods: 'POST')]
    public function initSudo(): JsonResponse
    {

        return new JsonResponse([
            'config' => [
                'app_version' => $this->config->getAppVersion(),
                'instance' => $this->internalConfig->getInstance()
            ]
        ]);

    }

}