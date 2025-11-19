<?php

namespace App\Service\Tls\Acme;

class PendingOrder
{

    public function __construct(
        public string $dnsRecordValue,
        public string $challengeUrl,
        public string $finalizeOrderUrl,
    ) {
    }

}