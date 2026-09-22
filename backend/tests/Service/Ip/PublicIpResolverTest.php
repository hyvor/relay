<?php

namespace App\Tests\Service\Ip;

use App\Service\Ip\ServerIpResolver\PublicIpResolver;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(PublicIpResolver::class)]
class PublicIpResolverTest extends KernelTestCase
{

    public function test_resolves_from_first_working_resolver(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient(
            new MockResponse('198.51.100.7'),
        ));

        $resolver = $this->getService(PublicIpResolver::class);
        $ip = $resolver->resolve();

        $this->assertSame('198.51.100.7', $ip);
    }

    public function test_falls_back_to_next_resolver_on_transport_error(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse('', ['error' => 'Connection timed out']),
            new MockResponse('203.0.113.5'),
        ]));

        $resolver = $this->getService(PublicIpResolver::class);
        $ip = $resolver->resolve();

        $this->assertSame('203.0.113.5', $ip);
    }

    public function test_falls_back_to_next_resolver_on_invalid_response(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse('not-an-ip-address'),
            new MockResponse('203.0.113.6'),
        ]));

        $resolver = $this->getService(PublicIpResolver::class);
        $ip = $resolver->resolve();

        $this->assertSame('203.0.113.6', $ip);
    }

    public function test_throws_when_all_resolvers_fail(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse('', ['error' => 'Connection timed out']),
            new MockResponse('not-an-ip-address'),
            new MockResponse('', ['error' => 'Connection refused']),
        ]));

        $resolver = $this->getService(PublicIpResolver::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to resolve public IP from all external services');

        $resolver->resolve();
    }

    public function test_throws_with_local_ip_in_message_when_given(): void
    {
        $this->container->set(HttpClientInterface::class, new MockHttpClient([
            new MockResponse('', ['error' => 'Connection timed out']),
            new MockResponse('', ['error' => 'Connection timed out']),
            new MockResponse('', ['error' => 'Connection timed out']),
        ]));

        $resolver = $this->getService(PublicIpResolver::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('for local IP 10.0.1.5');

        $resolver->resolve('10.0.1.5');
    }

    public function test_binds_requests_to_given_local_ip(): void
    {
        $mockResponse = new MockResponse('203.0.113.9');

        $this->container->set(HttpClientInterface::class, new MockHttpClient($mockResponse));

        $resolver = $this->getService(PublicIpResolver::class);
        $resolver->resolve('10.0.1.5');

        $this->assertSame('10.0.1.5', $mockResponse->getRequestOptions()['local_ip']);
    }

}
