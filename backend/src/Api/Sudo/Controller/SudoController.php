<?php

namespace App\Api\Sudo\Controller;

use App\Api\Sudo\Object\InstanceObject;
use App\Api\Sudo\Object\IpAddressObject;
use App\Api\Sudo\Object\ServerObject;
use App\Entity\IpAddress;
use App\Entity\Server;
use App\Service\App\Config;
use App\Service\Blacklist\IpBlacklists;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\WarmupScheduleService;
use App\Service\Server\ServerService;
use App\Service\Sudo\SudoPermission;
use Hyvor\Internal\Bundle\Api\SudoAuthorizationListener;
use Hyvor\Internal\Bundle\Api\SudoPermissionRequired;
use Hyvor\Internal\InternalConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[SudoPermissionRequired(SudoPermission::ACCESS_SUDO)]
class SudoController extends AbstractController
{

    public function __construct(
        private Config $config,
        private InternalConfig $internalConfig,
        private InstanceService $instanceService,
        private SudoAuthorizationListener $sudoAuthorizationListener,
        private ServerService $serverService,
        private IpAddressService $ipAddressService,
        private WarmupScheduleService $warmupScheduleService,
    ) {}

    #[Route('/init', methods: 'POST')]
    public function initSudo(): JsonResponse
    {
        $instance = $this->instanceService->getInstance();
        $user = $this->sudoAuthorizationListener->getResolvedUser();
        $instanceDomain = $this->config->getInstanceDomain();

        $servers = $this->serverService->getServers();
        $ipAddresses = $this->ipAddressService->getAllIpAddresses();
        $warmupSchedules = $this->warmupScheduleService->getCurrentWarmupSchedulesByIpAddresses($ipAddresses);

        return new JsonResponse([
            'config' => [
                'deployment' => $this->internalConfig->getDeployment()->value,
                'app_version' => $this->config->getAppVersion(),
                'instance' => $this->internalConfig->getInstance(),
                'blacklists' => IpBlacklists::getBlacklists(),
                'default_warmup_schedule' => WarmupScheduleService::DEFAULT_SCHEDULE,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? $user->username,
                    'email' => $user->email,
                    'picture_url' => $user->picture_url,
                ],
            ],
            'instance' => new InstanceObject($instance, $instanceDomain),
            'servers' => array_map(
                fn(Server $server) => new ServerObject($server),
                $servers,
            ),
            'ip_addresses' => array_map(
                fn(IpAddress $ipAddress) => new IpAddressObject(
                    $ipAddress,
                    $instanceDomain,
                    $warmupSchedules[$ipAddress->getId()] ?? null,
                ),
                $ipAddresses,
            ),
        ]);
    }
}
