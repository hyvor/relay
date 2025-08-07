<?php

namespace App\Tests\Api\Local\PrivateNetwork;

use App\Api\Local\Controller\PrivateNetworkController;
use App\Service\PrivateNetwork\GoHttpApi;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ServerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(PrivateNetworkController::class)]
#[CoversClass(GoHttpApi::class)]
class UpdateStateTest extends WebTestCase
{

    public function test_updates_state(): void
    {
        $this->setConfig('envHostname', 'goodserver');

        ServerFactory::createOne(['hostname' => 'goodserver']);

        $response = new MockResponse('{}');
        $httpClient = new MockHttpClient($response);
        $this->container->set(HttpClientInterface::class, $httpClient);

        $this->localApi(
            'POST',
            '/state/update',
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('http://localhost:8085/state', $response->getRequestUrl());

        $body = $response->getRequestOptions()['body'];
        $this->assertIsString($body);
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('hostname', $data);
        $this->assertSame('goodserver', $data['hostname']);
    }

    public function test_with_custom_go_host_and_failed_http(): void
    {
        $this->setConfig('envHostname', 'goodserver');
        $this->setConfig('goHost', 'go.com');

        ServerFactory::createOne(['hostname' => 'goodserver']);

        $response = new MockResponse('{}', ['http_code' => 401]);
        $httpClient = new MockHttpClient($response);
        $this->container->set(HttpClientInterface::class, $httpClient);

        $this->localApi(
            'POST',
            '/state/update',
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('http://go.com:8085/state', $response->getRequestUrl());
        $this->assertSame(
            'Failed to call go HTTP API: HTTP 401 returned for "http://go.com:8085/state". Response: {}',
            $this->getJson()['message']
        );
    }

    public function test_when_server_not_initialized(): void
    {
        $this->localApi(
            'POST',
            '/state/update',
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(
            'Server not yet initialized',
            $this->getJson()['message']
        );
    }

}