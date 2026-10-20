<?php

namespace App\Service\Management\GoState;

class GoStateIp
{

    public function __construct(
        // IP address ID
        public int $id,

        // Public IP address (used for PTR/EHLO and metrics)
        public string $ip,

        // Private IP address for binding the SMTP connection (null if not using NAT)
        public ?string $privateIp,

        // ptr domain (same as EHLO domain)
        public string $ptr,

        // email queue id, name to send email from this IP
        public int $queueId,
        public string $queueName,
    )
    {
    }

}