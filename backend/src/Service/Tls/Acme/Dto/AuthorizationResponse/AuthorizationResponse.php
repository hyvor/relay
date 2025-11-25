<?php

namespace App\Service\Tls\Acme\Dto\AuthorizationResponse;

use App\Service\Tls\Acme\Exception\AcmeException;

class AuthorizationResponse
{


    public string $status;
    /**
     * @var Challenge[]
     */
    public array $challenges;

    /**
     * @throws AcmeException
     */
    public function getFirstDns01Challenge(): Challenge
    {
        foreach ($this->challenges as $challenge) {
            if ($challenge->type === 'dns-01') {
                return $challenge;
            }
        }
        throw new AcmeException('No dns-01 challenge found in authorization response'); // @codeCoverageIgnore
    }

}