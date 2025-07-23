<?php

namespace App\Service\Management\GoState;

class GoStateIp
{

    public function __construct(
        // IP address ID
        public int $id,

        // IP address
        public string $ip,

        // ptr domain (same as EHLO domain)
        public string $ptr,

        // email queue id, name to send email from this IP
        public int $queueId,
        public string $queueName,
    )
    {
    }

}