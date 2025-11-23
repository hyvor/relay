<?php

namespace App\Tests\Service\Tls\Acme;

use App\Service\Tls\Acme\Dto\FinalCertificate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinalCertificate::class)]
class FinalCertificateTest extends TestCase
{

    public function test_pem_to_cert(): void
    {
        $cert = <<<EOT
-----BEGIN CERTIFICATE-----
MIIDKTCCAhGgAwIBAgIUGJ6i91LcEx6740j08+nPqxE+f3UwDQYJKoZIhvcNAQEL
BQAwJDESMBAGA1UEAwwJaHl2b3IuY29tMQ4wDAYDVQQKDAVIWVZPUjAeFw0yNTEx
MjMwMjQ3MTVaFw0zNTExMjEwMjQ3MTVaMCQxEjAQBgNVBAMMCWh5dm9yLmNvbTEO
MAwGA1UECgwFSFlWT1IwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCe
H29GkCSqi3hGDvHzr320OjPzcUXkeicSdalNcDviRpUs/xJSDdkoFspI4h8UHs/k
2BOIF3YkF7eOTfNJGxbKmTbe+Kajql8pMN+8bNsZW/b+VAcHHJUPEYNhRfssFiXU
leYKdglE5kY5TzlPkyiqSUz1P389EzxWq73il7LYAR9rIrKpAF19FrWau3ZJAQMb
mBhUO7gvrc5KpGnz/E93luqy9UV6jmwbCeMjw53euIoGpCWD3JsouB8c2wwCM+wL
y3GmIXDixnhyjnVlw6z7kWVFzPb7m4hbwVftp9ygJ1QJ8nU7qTN2mnEifcQZ0/03
tdYjVH5PmdOOL6lnKpxdAgMBAAGjUzBRMB0GA1UdDgQWBBTt7chfW794DJmeVBXq
LWu0/iAhpjAfBgNVHSMEGDAWgBTt7chfW794DJmeVBXqLWu0/iAhpjAPBgNVHRMB
Af8EBTADAQH/MA0GCSqGSIb3DQEBCwUAA4IBAQBl1Dw7x4btGBtCKWPZo+WwLGbN
nA8bGaFgEXOZz0ZOrudv8yhNjUqJ+zrFuvzauLhT7FsXa5I0MnFhLSMDOTI/KAYE
/f5GLLMIO/dzMO2lrwFrgI0sEiLPTmXMU62xrH1U1CCbEiiGURvcUDfYL8rFchUQ
B3S/KgEtWir6jVo9dGiBNFpC77Lm1pLK2QTBzfJGQvJq/lFf7mVSsqVPVfBSRbkf
3bCH24z6Gq0H7y2Iqfa1G7FmL1ez2y0ITVl1g1BBcITWNXuySAFtcJdD3oYGZRXu
g7xOtK5iC62rznwc3C99ohGLW7PjGF6OlLiPJbjGi0bFf1y3lcH/dzVX7XHW
-----END CERTIFICATE-----
EOT;

        $finalCert = FinalCertificate::fromPem($cert);
        $this->assertSame($cert, $finalCert->certificatePem);
        $this->assertSame('2025-11-23 02:47:15', $finalCert->validFrom->format('Y-m-d H:i:s'));
        $this->assertSame('2035-11-21 02:47:15', $finalCert->validTo->format('Y-m-d H:i:s'));
    }

}