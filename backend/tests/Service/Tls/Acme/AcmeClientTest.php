<?php

namespace App\Tests\Service\Tls\Acme;

use App\Entity\Type\DnsRecordType;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Service\Tls\Acme\AcmeClient;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(AcmeClient::class)]
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

        $certificateResponse = new MockResponse("-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----");

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
                $finalizeOrderResponse,
                $nonceResponse(7),
                $orderValidResponse,
                $nonceResponse(8),
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
            ->willReturn(
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
            $cert
        );


        // test HTTP requests made
        $this->assertSame(
            AcmeClient::DIRECTORY_URL_LETSENCRYPT_STAGING,
            $directoryResponse->getRequestUrl()
        );
    }

}