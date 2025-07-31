<?php

namespace App\Service\Management\GoState;

/**
 * State of the Go service.
 */
class GoState
{

    public function __construct(
        /**
         * The domain of the Hyvor Relay instance (a.k.a. Primary Domain)
         */
        public string $instanceDomain,

        /**
         * Hostname of the current server
         */
        public string $hostname,

        /**
         * Available, active, queue-assigned IP addresses of the current server.
         * @var GoStateIp[]
         */
        public array $ips,

        /**
         * The number of workers for each IP address.
         */
        public int $emailWorkersPerIp,

        /**
         * The number of workers for the webhook queue (global)
         */
        public int $webhookWorkers,

        /**
         * The number of maximum workers to handle incoming mail
         */
        public int $incomingWorkers,

        /**
         * first server is always the leader
         * it exposes global metrics
         */
        public bool $isLeader,

        /**
         * Whether to run the DNS server and data for it
         */
        public string $dnsIp, // empty if no IP
        /** @var GoStateDnsRecord[] */
        public array $dnsRecords,

        public int $serversCount,
        public string $env,
        public string $version,
    )
    {
    }


}