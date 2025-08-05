<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\DefaultDnsRecordObject;
use App\Api\Sudo\Object\InstanceObject;
use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Management\GoState\GoStateDnsRecordsService;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SudoController extends AbstractController
{

    public function __construct(
        private Config $config,
        private InternalConfig $internalConfig,
        private InstanceService $instanceService,
        private GoStateDnsRecordsService $goStateDnsRecordsService
    ) {
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

    #[Route('/default-dns', methods: 'GET')]
    public function getDefaultDns(): JsonResponse
    {
        $instance = $this->instanceService->getInstance();
        $dnsRecords = $this->goStateDnsRecordsService->getDnsRecords($instance);

        return new JsonResponse(
            array_map(fn($record) => new DefaultDnsRecordObject($record), $dnsRecords)
        );
    }

}
