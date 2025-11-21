<?php

namespace App\Service\Tls\Acme\Dto\AuthorizationResponse;

readonly class Challenge
{

    public function __construct(
        /**
         * @var string 'http-01'|'dns-01'|'tls-alpn-01'
         */
        public string $type,
        public string $token,
        public string $url,
    ) {
    }
}