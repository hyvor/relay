<?php

declare(strict_types=1);

namespace App\Service\Tls\Acme\Dto;

readonly class DirectoryDto
{
    public function __construct(
        public string $newNonce,
        public string $newAccount,
        public string $newOrder,
        public string $revokeCert,
        public string $keyChange,
    ) {
    }

}
