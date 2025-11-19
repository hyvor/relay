<?php

namespace App\Service\Tls\Acme\Dto;

readonly class AccountInternalDto
{

    public function __construct(
        public string $privateKeyEncrypted,
        public ?string $kid,
    ) {
    }

}