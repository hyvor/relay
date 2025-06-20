<?php

namespace App\Service\Management\GoState;

class GoStateIp
{

    public function __construct(
        // IP address
        public string $ip,

        // ptr domain (same as EHLO domain)
        public string $ptr,

        // email queue name to send email from this IP
        public string $queue,

        // whether the IP should handle incoming emails
        public bool $incoming,
    )
    {
    }

}