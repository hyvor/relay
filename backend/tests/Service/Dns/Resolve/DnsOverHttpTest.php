<?php

namespace App\Tests\Service\Dns\Resolve;

use App\Service\Dns\Resolve\DnsOverHttp;
use App\Service\Dns\Resolve\DnsResolveInterface;
use App\Service\Dns\Resolve\DnsResolvingFailedException;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Dns\Resolve\ResolveAnswer;
use App\Service\Dns\Resolve\ResolveResult;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(DnsOverHttp::class)]
#[CoversClass(ResolveResult::class)]
#[CoversClass(ResolveAnswer::class)]
class DnsOverHttpTest extends KernelTestCase
{

    public function test_dns_success(): void
    {
        $mockResponse = new JsonMockResponse([
            'Status' => 0,
            'Answer' => [
                [
                    'name' => 'example.com',
                    'type' => 28,
                    'TTL' => 300,
                    'data' => '2606:2800:220:1:248:1893:25c8:1946'
                ]
            ]
        ]);


        $httpClient = new MockHttpClient($mockResponse);
        $this->container->set(HttpClientInterface::class, $httpClient);

        /** @var DnsResolveInterface $dnsOverHttp */
        $dnsOverHttp = $this->container->get(DnsResolveInterface::class);
        $result = $dnsOverHttp->resolve('example.com', DnsType::A);

        $this->assertSame(0, $result->status);
        $this->assertTrue($result->ok());

        $answers = $result->answers;
        $this->assertCount(1, $answers);
        $this->assertSame('example.com', $answers[0]->name);
        $this->assertSame('2606:2800:220:1:248:1893:25c8:1946', $answers[0]->data);
        $this->assertSame(28, $answers[0]->type);
        $this->assertSame(300, $answers[0]->ttl);

    }

    public function test_dns_fail_nxdomain(): void
    {
        $mockResponse = new JsonMockResponse([
            'Status' => 3,
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $this->container->set(HttpClientInterface::class, $httpClient);

        /** @var DnsOverHttp $dnsOverHttp */
        $dnsOverHttp = $this->container->get(DnsOverHttp::class);
        $result = $dnsOverHttp->resolve('nonexistentdomain.com', DnsType::A);

        $this->assertSame(3, $result->status);
        $this->assertFalse($result->ok());
        $this->assertSame('Non-existent domain (NXDOMAIN)', $result->error());
    }

    public function test_dns_fail_http_error(): void
    {
        $mockResponse = new JsonMockResponse(null, [
            'http_code' => 500,
            'error' => 'Network error'
        ]);

        $httpClient = new MockHttpClient($mockResponse);
        $this->container->set(HttpClientInterface::class, $httpClient);

        /** @var DnsOverHttp $dnsOverHttp */
        $dnsOverHttp = $this->container->get(DnsOverHttp::class);

        $this->expectException(DnsResolvingFailedException::class);
        $this->expectExceptionMessage('Network error');

        $dnsOverHttp->resolve('example.com', DnsType::A);
    }

}