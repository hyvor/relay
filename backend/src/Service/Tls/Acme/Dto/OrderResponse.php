<?php

namespace App\Service\Tls\Acme\Dto;

use App\Service\Tls\Acme\Exception\AcmeException;

readonly class OrderResponse
{

    /**
     * @param string[] $authorizations
     */
    public function __construct(
        public string $status,
        public string $finalize,
        public array $authorizations,
        public string $certificate,
    ) {
    }

    /**
     * @throws AcmeException
     */
    public function firstAuthorizationUrl(): string
    {
        if (count($this->authorizations) === 0) {
            throw new AcmeException('No authorizations found in order response');
        }

        return $this->authorizations[0];
    }

}