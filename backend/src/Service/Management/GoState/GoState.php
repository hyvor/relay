<?php

namespace App\Service\Management\GoState;

/**
 * State of the Go service.
 */
class GoState
{

    public function __construct(
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
    )
    {
    }


}