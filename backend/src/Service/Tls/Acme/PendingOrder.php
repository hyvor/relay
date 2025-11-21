<?php

namespace App\Service\Tls\Acme;

class PendingOrder
{

    public function __construct(
        public string $domain,
        public string $dnsRecordValue,
        public string $orderUrl, // to be used to poll order status
        public string $challengeUrl, // first notified here that the challenge is ready
        public string $authorizationUrl, // to be polled for status
        public string $finalizeOrderUrl, // to be used to finalize the order
    )
    {
    }

}