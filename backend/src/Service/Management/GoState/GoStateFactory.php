<?php

namespace App\Service\Management\GoState;

use App\Service\App\Config;
use App\Service\Instance\InstanceService;
use App\Service\Ip\IpAddressService;
use App\Service\Ip\Ptr;
use App\Service\Server\ServerService;
use App\Service\Tls\TlsCertificateService;

class GoStateFactory
{

    public function __construct(
        private ServerService $serverService,
        private IpAddressService $ipAddressService,
        private InstanceService $instanceService,
        private Config $config,
        private GoStateDnsRecordsService $goStateService,
        private TlsCertificateService $tlsCertificateService
    ) {
    }

    public function create(): GoState
    {
        $instance = $this->instanceService->getInstance();
        $mailTlsCert = $this->tlsCertificateService->getInstanceMailTlsCertificate($instance);

        $server = $this->serverService->getServerByCurrentHostname();

        if ($server === null) {
            throw new ServerNotFoundException();
        }

        $isLeader = $this->serverService->isServerLeader($server);
        $ips = [];

        $ipsFromServer = $this->ipAddressService->getIpAddressesOfServer($server);

        foreach ($ipsFromServer as $ip) {
            $queue = $ip->getQueue();

            if ($queue === null) {
                continue;
            }

            $ips[] = new GoStateIp(
                id: $ip->getId(),
                ip: $ip->getIpAddress(),
                ptr: Ptr::getPtrDomain($ip, $this->config->getInstanceDomain()),
                queueId: $queue->getId(),
                queueName: $queue->getName(),
            );
        }

        $dnsIp = count($ips) > 0 ? $ips[0]->ip : "";

        return new GoState(
            instanceDomain: $this->config->getInstanceDomain(),
            hostname: $server->getHostname(),
            ips: $ips,
            apiWorkers: $server->getApiWorkers(),
            emailWorkersPerIp: $server->getEmailWorkers(),
            webhookWorkers: $server->getWebhookWorkers(),
            incomingWorkers: $server->getIncomingWorkers(),
            isLeader: $isLeader,

            // mail server settings
            mailTls: $mailTlsCert === null ?
                [
                    'enabled' => false,
                    'privateKey' => '',
                    'certificate' => '',
                ] :
                [
                    'enabled' => true,
                    'privateKey' => $this->tlsCertificateService->getDecryptedPrivateKeyPem($mailTlsCert),
                    'certificate' => $mailTlsCert->getCertificate() ?? '',
                ],

            // data for the DNS server
            dnsIp: $dnsIp,
            dnsRecords: $this->goStateService->getDnsRecords($instance),

            serversCount: $this->serverService->getServersCount(),
            env: $this->config->getEnv(),
            version: $this->config->getAppVersion(),
        );
    }

}
