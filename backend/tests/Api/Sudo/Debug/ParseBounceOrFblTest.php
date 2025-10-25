<?php

namespace App\Tests\Api\Sudo\Debug;

use App\Api\Sudo\Controller\DebugController;
use App\Service\Go\GoHttpApi;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(DebugController::class)]
#[CoversClass(GoHttpApi::class)]
class ParseBounceOrFblTest extends WebTestCase
{

    public function test_parse_bounce_or_fbl_endpoint(): void
    {
        $response = new JsonMockResponse(['status' => 'parsed']);
        $this->container->set(HttpClientInterface::class, new MockHttpClient($response));

        $payload = [
            'raw' => "From: supun@hyvor.com",
            'type' => 'bounce',
        ];

        $this->sudoApi("POST", "/debug/parse-bounce-fbl", $payload);

        $this->assertResponseStatusCodeSame(200);

        $json = $this->getJson();
        $this->assertSame(['status' => 'parsed'], $json['parsed']);
    }

    public function test_on_go_error_parse_bounce_or_fbl_returns_422(): void
    {
        $response = new JsonMockResponse(
            ['error' => 'Go service error'],
            ['http_code' => 500]
        );
        $this->container->set(HttpClientInterface::class, new MockHttpClient($response));

        $payload = [
            'raw' => "From: supun@hyvor.com",
            'type' => 'bounce',
        ];

        $this->sudoApi("POST", "/debug/parse-bounce-fbl", $payload);

        $this->assertResponseStatusCodeSame(422);
    }
}