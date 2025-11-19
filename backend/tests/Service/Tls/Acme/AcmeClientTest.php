<?php

namespace App\Tests\Service\Tls\Acme;

use App\Service\Tls\Acme\AcmeClient;
use App\Service\Tls\Acme\Exception\AcmeException;
use App\Tests\Case\KernelTestCase;

class AcmeClientTest extends KernelTestCase
{

    public function test_acme_client_init(): void
    {
        $client = $this->getService(AcmeClient::class);
        $client->init();
    }

}