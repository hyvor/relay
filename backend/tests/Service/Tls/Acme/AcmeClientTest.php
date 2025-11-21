<?php

namespace App\Tests\Service\Tls\Acme;

use App\Service\Tls\Acme\AcmeClient;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(AcmeClient::class)]
class AcmeClientTest extends KernelTestCase
{

    public function test_acme_client_init(): void
    {
        $directoryResponse = new JsonMockResponse([
            'newAccount' => 'https://acme.org/newAccount',
            'newOrder' => 'https://acme.org/newOrder',
            'newNonce' => 'https://acme.org/newNonce',
            'revokeCert' => 'https://acme.org/revokeCert',
            'keyChange' => 'https://acme.org/keyChange',
        ]);

        $nonceResponse = new MockResponse(info: [
            'response_headers' => [
                'Replay-Nonce' => ['test-nonce-123']
            ]
        ]);

        $newAccountResponse = new JsonMockResponse([], info: [
            'response_headers' => [
                'Location' => ['https://acme.org/acct/1'] // kid
            ]
        ]);

        $newOrderResponse = new JsonMockResponse([
            'status' => 'pending',
            'authorizations' => [
                'https://acme.org/authz/1'
            ],
            'finalize' => 'https://acme.org/finalize/1',
        ]);

        $authorizationUrlResponse = new JsonMockResponse([
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

        $this->container->set(
            HttpClientInterface::class,
            new MockHttpClient([
                $directoryResponse,
                $nonceResponse,
                $newAccountResponse,
                $newOrderResponse,
                $authorizationUrlResponse,
            ])
        );

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

        // directory
        $this->assertSame(
            AcmeClient::DIRECTORY_URL_LETSENCRYPT_STAGING,
            $directoryResponse->getRequestUrl()
        );
    }

}