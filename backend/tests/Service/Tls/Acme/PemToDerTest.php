<?php

namespace App\Tests\Service\Tls\Acme;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class PemToDerTest extends TestCase
{


    public function test_pem_to_der(): void
    {
        $pem = file_get_contents('tmp/test.pem');
        $der = pem2der($pem);

        $this->assertSame(
            $der,
            file_get_contents('tmp/test.der')
        );
    }

}

function pem2der($pem_data)
{
    $begin = "-----BEGIN CERTIFICATE REQUEST-----";
    $end = "-----END CERTIFICATE REQUEST-----";
    $pem_data = substr($pem_data, strpos($pem_data, $begin) + strlen($begin));
    $pem_data = substr($pem_data, 0, strpos($pem_data, $end));
    $der = base64_decode($pem_data);
    return $der;
}