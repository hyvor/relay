<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Authorization\SudoAuthorizationListener;
use App\Api\Sudo\Object\DefaultDnsRecordObject;
use App\Api\Sudo\Object\InstanceObject;
use App\Service\App\Config;
use App\Service\Blacklist\IpBlacklists;
use App\Service\Instance\InstanceService;
use App\Service\Management\GoState\GoStateDnsRecordsService;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function initSudo(Request $request): JsonResponse
    {
        $instance = $this->instanceService->getInstance();
        $user = SudoAuthorizationListener::getUser($request);

        return new JsonResponse([
            'config' => [
                'hosting' => $this->config->getHosting(),
                'app_version' => $this->config->getAppVersion(),
                'instance' => $this->internalConfig->getInstance(),
                'blacklists' => IpBlacklists::getBlacklists(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->username,
                    'email' => $user->email,
                    'picture_url' => $user->picture_url,
                ]
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
