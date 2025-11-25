<?php

namespace App\Tests\Service\Tls\Acme;

use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Tls\Acme\AcmeClient;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversNamespace;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversNamespace("App\Service\Tls\Acme")]
class AcmeClientTest extends KernelTestCase
{

    public function test_acme_client_happy_path(): void
    {
        Clock::set(new MockClock());

        $directoryResponse = new JsonMockResponse([
            'newAccount' => 'https://acme.org/newAccount',
            'newOrder' => 'https://acme.org/newOrder',
            'newNonce' => 'https://acme.org/newNonce',
            'revokeCert' => 'https://acme.org/revokeCert',
            'keyChange' => 'https://acme.org/keyChange',
        ]);

        $nonceResponse = fn(int $num) => new MockResponse(info: [
            'response_headers' => [
                'Replay-Nonce' => ['test-nonce-' . $num]
            ]
        ]);

        $newAccountResponse = new JsonMockResponse([], info: [
            'response_headers' => [
                'Location' => ['https://acme.org/acct/1'] // kid
            ]
        ]);

        $newOrderResponse = new JsonMockResponse(
            [
                'status' => 'pending',
                'authorizations' => [
                    'https://acme.org/authz/1'
                ],
                'finalize' => 'https://acme.org/finalize/1',
            ],
            info: [
                'response_headers' => [
                    'Location' => ['https://acme.org/order/1']
                ]
            ]
        );

        $authorizationUrlFirstResponse = new JsonMockResponse([
            'identifier' => [
                'type' => 'dns',
                'value' => 'myinstance.com',
            ],
            'status' => 'pending',
            'expires' => '2024-12-31T23:59:59Z',
            'challenges' => [
                [
                    'type' => 'dns-01',
                    'url' => 'https://acme.org/challenge/1',
                    'status' => 'pending',
                    'token' => 'challenge-token-123',
                ],
            ],
        ]);

        $challengeResponse = new JsonMockResponse([]);

        $authorizationUrlSecondResponse = new JsonMockResponse([
            'status' => 'pending',
            'challenges' => [],
        ]);

        $authorizationUrlThirdResponse = new JsonMockResponse([
            'status' => 'valid',
            'challenges' => [],
        ]);

        $finalizeOrderResponse = new JsonMockResponse([]);

        $orderValidResponse = new JsonMockResponse([
            'status' => 'valid',
            'finalize' => 'https://acme.org/finalize/1',
            'authorizations' => [],
            'certificate' => 'https://acme.org/cert/1',
        ]);

        $certificateResponse = new MockResponse(self::PEM_CERTIFICATE_SAMPLE);

        $this->container->set(
            HttpClientInterface::class,
            new MockHttpClient([
                $directoryResponse,
                $nonceResponse(1),
                $newAccountResponse,
                $nonceResponse(2),
                $newOrderResponse,
                $nonceResponse(3),
                $authorizationUrlFirstResponse,
                $nonceResponse(4),
                $challengeResponse,
                $nonceResponse(5),
                $authorizationUrlSecondResponse,
                $nonceResponse(6),
                $authorizationUrlThirdResponse,
                $nonceResponse(7),
                $finalizeOrderResponse,
                $nonceResponse(8),
                $orderValidResponse,
                $nonceResponse(9),
                $certificateResponse,
            ])
        );

        $dnsResolver = $this->createMock(DnsResolveInterface::class);
        $this->container->set(DnsResolveInterface::class, $dnsResolver);

        $client = $this->getService(AcmeClient::class);
        $client->init();
        $pendingOrder = $client->newOrder('myinstance.com');

        $this->assertSame(
            'https://acme.org/challenge/1',
            $pendingOrder->challengeUrl
        );
        $this->assertSame(
            'https://acme.org/finalize/1',
            $pendingOrder->finalizeOrderUrl
        );

        $pkey = openssl_pkey_new();
        assert($pkey !== false);

        $dnsResolver->method('resolve')
            ->with('_acme-challenge.myinstance.com', DnsType::TXT)
            ->willReturnOnConsecutiveCalls(
            // wrong records in the first call
                new ResolveResult(0, [
                    new ResolveAnswer(
                        "_acme-challenge.myinstance.com",
                        "wrong-dns-value",
                    )
                ]),
                // second call returns the correct record
                new ResolveResult(0, [
                    new ResolveAnswer(
                        "_acme-challenge.myinstance.com",
                        $pendingOrder->dnsRecordValue,
                    )
                ])
            );

        // finalize order
        $cert = $client->finalizeOrder($pendingOrder, $pkey);
        $this->assertStringContainsString(
            "-----BEGIN CERTIFICATE-----",
            $cert->certificatePem
        );

        // test HTTP requests made
        $this->assertSame(
            AcmeClient::DIRECTORY_URL_LETSENCRYPT_STAGING,
            $directoryResponse->getRequestUrl()
        );
    }


    // DATA

    public const string PEM_CERTIFICATE_SAMPLE = <<<EOT
-----BEGIN CERTIFICATE-----
MIIFQjCCBCqgAwIBAgISLN/KRgzNY5rzTbuApEmkbGyfMA0GCSqGSIb3DQEBCwUA
MFgxCzAJBgNVBAYTAlVTMSAwHgYDVQQKExcoU1RBR0lORykgTGV0J3MgRW5jcnlw
dDEnMCUGA1UEAxMeKFNUQUdJTkcpIFJpZGRsaW5nIFJodWJhcmIgUjEyMB4XDTI1
MTEyNTAyNTkxMVoXDTI2MDIyMzAyNTkxMFowJzElMCMGA1UEAxMcbXgubWFpbHRl
c3QuaHl2b3JzdGFnaW5nLmNvbTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC
ggEBAOlgRpA9FpdPlxkucP6ekHTwctIkeLBTKu5o1eFgeV7nUfPh6gzrVJVw9eyF
PzlxiGT1SF6aZgC2QiNtoOpzy3N3RcglaKYb2XdTtvlOEi+ABQfvsa2zzGuc/mvo
UJCICO+9izuNMpipk7C0XzZI9flqkhI9zDLQP7iOlceGRgWyo4tQVnR6JQ01ms2L
yFA2SHd5C6jsY/x3b2y8xpqPS7ctfyVLMOMobJS5v9lee2NZOo4huorcr4CCtZ2g
D4ZBlk2vRqOvn/Q3wESa3B+rZehDBmJwPyyAhpSJv0zeJ7mBUsIvkdlIjifdnatt
1PqSQZzd2amHTLRWGUPPIZ2T9VkCAwEAAaOCAjUwggIxMA4GA1UdDwEB/wQEAwIF
oDAdBgNVHSUEFjAUBggrBgEFBQcDAQYIKwYBBQUHAwIwDAYDVR0TAQH/BAIwADAd
BgNVHQ4EFgQU8WXY4gKUNp1jcT+9GOLxrZi7/aAwHwYDVR0jBBgwFoAU9kMDkS9m
Ja+FJd3kZNFpfsuiHNkwNwYIKwYBBQUHAQEEKzApMCcGCCsGAQUFBzAChhtodHRw
Oi8vc3RnLXIxMi5pLmxlbmNyLm9yZy8wJwYDVR0RBCAwHoIcbXgubWFpbHRlc3Qu
aHl2b3JzdGFnaW5nLmNvbTATBgNVHSAEDDAKMAgGBmeBDAECATAyBgNVHR8EKzAp
MCegJaAjhiFodHRwOi8vc3RnLXIxMi5jLmxlbmNyLm9yZy8zMi5jcmwwggEFBgor
BgEEAdZ5AgQCBIH2BIHzAPEAdwAW6GnB0ZXq18P4lxrj8HYB94zhtp0xqFIYtoN/
MagVCAAAAZq5KPgAAAAEAwBIMEYCIQCYvAfRshjHgUX4wxfHOiWghUaZlru0xACl
Q+Cpit1O5gIhAKJjy9xEpUkbfw4sZouRLeucns1wtWCtrP7KyRUZA7wjAHYAsMyD
5aX5fWuvfAnMKEkEhyrH6IsTLGNQt8b9JuFsbHcAAAGauSj37gAABAMARzBFAiBH
M11ZWGNgsNCd0Zzt1+Kq3mehU83IQpASLVVrc2a/cgIhAJ08mWKqe3q6VJ0LUQoi
zh/te0Pi2vJZfIRnoHCgFbiNMA0GCSqGSIb3DQEBCwUAA4IBAQAIfhxtkkwJYmpo
lqdY4YQMO681SLAP+Rf9ahkmLNo9DwhEaayqCK/WsJPaOqs1aAHSAZvY+u27EPmI
E7UjRUXdmXueDjzTC3e94GiecFpjaOj7Ypn4TZvQafVQWwi0bhjM/rQpWT8sA7AQ
tPq2kw+iFqbplozaDBslo9A6lLR0axcbUfFoEdLHOwctjbVNqiiS3Iva5DMdb0CO
NyPjDylFLagLMJsf7qp/mxUYu2oZTTaJxfOceppbtW/G+wlNNCbTneir5S0BXt88
QT4SMlPgSazTjxuw6VKMMLPRAWmtdJiNRL57pHyK4A5tdK1YiVqXQZuGNSgM2oYU
Ja3j4HN3
-----END CERTIFICATE-----

-----BEGIN CERTIFICATE-----
MIIFSjCCAzKgAwIBAgIQG+VMtXt5SUBCqOW+UHI8WTANBgkqhkiG9w0BAQsFADBm
MQswCQYDVQQGEwJVUzEzMDEGA1UEChMqKFNUQUdJTkcpIEludGVybmV0IFNlY3Vy
aXR5IFJlc2VhcmNoIEdyb3VwMSIwIAYDVQQDExkoU1RBR0lORykgUHJldGVuZCBQ
ZWFyIFgxMB4XDTI0MDMxMzAwMDAwMFoXDTI3MDMxMjIzNTk1OVowWDELMAkGA1UE
BhMCVVMxIDAeBgNVBAoTFyhTVEFHSU5HKSBMZXQncyBFbmNyeXB0MScwJQYDVQQD
Ex4oU1RBR0lORykgUmlkZGxpbmcgUmh1YmFyYiBSMTIwggEiMA0GCSqGSIb3DQEB
AQUAA4IBDwAwggEKAoIBAQDYHSGRScFq1U7sAS8YfqaA2I/hnt1bHeDT3/lnQcQ4
U24ic0bSHmDECLIgQh8b4HhKG0G5X9P7+8JQgWQbizkNJYps3IOwhqiG0FmPvR1m
/byeEtidU0aVtAQoLNIkHulaw1l3nCIBGtL5ZGGKzOGP39VRNp2iV4a1TIGnb0/F
z08FWhvkAxaX6WP8HH9aFvvsnjH14D+O24WBJjqHe5V8+IiLoxM9EcRCgOk3oAS2
VngAnoSqkqeHKoIIz8t761lEk34Ln8qhEEHCxaX44Y4glllbLQL1fN1tEUiV7Gfw
W4JU644Em2RkzHr5G/rD6aam6EXgmIq4Nucj9IENjN7hAgMBAAGjggEAMIH9MA4G
A1UdDwEB/wQEAwIBhjAdBgNVHSUEFjAUBggrBgEFBQcDAgYIKwYBBQUHAwEwEgYD
VR0TAQH/BAgwBgEB/wIBADAdBgNVHQ4EFgQU9kMDkS9mJa+FJd3kZNFpfsuiHNkw
HwYDVR0jBBgwFoAUtfNl8v6wCpIf+zx980SgrGMlwxQwNgYIKwYBBQUHAQEEKjAo
MCYGCCsGAQUFBzAChhpodHRwOi8vc3RnLXgxLmkubGVuY3Iub3JnLzATBgNVHSAE
DDAKMAgGBmeBDAECATArBgNVHR8EJDAiMCCgHqAchhpodHRwOi8vc3RnLXgxLmMu
bGVuY3Iub3JnLzANBgkqhkiG9w0BAQsFAAOCAgEArPTkhI92+dUqNgxVitYi/w8z
xwOXR6rhiJvKaoOFhA8DxqUaQ12eIVrhIN8LSQq2+O/Su3+VQaLXuQoeh/RBts6m
GQS56fTuf6Q7Sb49olfNQjkdV+BqxkUzyWl7j/GAGumpZxGPLRmfUT0BV1jq+2Yd
HKjvo3smWPEBnx/XMQ+p+fXh3xL1ATgB+eFtgQ/mQkVLMDUWGrcHUy78LTVgTZmt
eWhTubOipgNVJi19FHi3cYdsPTM3JOCAGahsXOYV30sU+9TKw+fEG1RgjucVAiPC
VxoyT/xiQ28tJ+MjWodlhrdWlTmiLHm6uBqJV8cEh6v0DwlI6BdnbCqM44d+jsO9
QjlPiG6uGX9LEYaVmgIA3cplNBdWZ3KdYNnxh+bVFz9aO8j1G5kDTNldx6nMP+u5
t1rA0iHIVMhXbY2JALAbO6/kH8XWmghsC81Xr5PzJEnROKZ65daBItHambNl+JJc
mj84Iqe4HIjgOn2/BbfQHu/YfQx8gZGl5nXarDkGtI3IS4jb9OPfJZEErnizRVAV
KsqiDk04Teh3BZX/w2prXHGCdsxCbbQgfximi0Q/Ug1xE0HCmmdTq/ZgYb2zdu3m
s6Sg00ivj7b0LaPtVGt1eEFqX+6OofleMic4uZgiazH/87j0EhBquPEmNPeXEMq1
SIKYbiG+Lshx0NWHFGE=
-----END CERTIFICATE-----
EOT;


}