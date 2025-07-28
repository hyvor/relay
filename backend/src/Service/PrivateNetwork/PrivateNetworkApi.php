<?php

namespace App\Service\PrivateNetwork;

use App\Entity\Server;
use App\Service\PrivateNetwork\Exception\PrivateNetworkCallException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrivateNetworkApi
{

    public function __construct(
        private HttpClientInterface $httpClient
    )
    {
    }

    /**
     * @throws PrivateNetworkCallException
     * @return array<mixed>
     */
    private function callApiOfServer(
        Server $server,
        string $method,
        string $endpoint, // part after /api/local
    ): array
    {

        $privateIp = $server->getPrivateIp();

        if ($privateIp === null) {
            throw new PrivateNetworkCallException('Server does not have a private IP address assigned.');
        }

        $endpoint = trim($endpoint, '/');
        $url = sprintf('http://%s/api/local/%s', $privateIp, $endpoint);

        try {
            $response = $this->httpClient->request($method, $url);
            return $response->toArray();
        } catch (ExceptionInterface $e) {

            $errorContent = 'No content';
            try {
                $errorContent = isset($response) ? $response->getContent() : 'No response';
            } catch (ExceptionInterface) {}

            throw new PrivateNetworkCallException(
                sprintf(
                    'Failed to call private network API: %s, Content: %s',
                    $e->getMessage(),
                    $errorContent
                ),
                previous: $e
            );
        }

    }

    /**
     * @throws PrivateNetworkCallException
     */
    public function callUpdateServerStateApi(Server $server): void
    {
        $this->callApiOfServer($server, 'POST', '/state/update');
    }

}