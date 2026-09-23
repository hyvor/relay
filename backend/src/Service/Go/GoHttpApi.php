<?php

namespace App\Service\Go;

use App\Entity\Type\DebugIncomingEmailType;
use App\Service\App\Config;
use App\Service\Dns\Resolve\DnsType;
use App\Service\Management\GoState\GoState;
use App\Service\Go\Exception\GoHttpCallException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoHttpApi
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private Config $config,
    ) {
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws GoHttpCallException
     */
    private function callApi(string $endpoint, array $data): array
    {
        $endpoint = trim($endpoint, '/');

        $goHost = $this->config->getGoHost() ?? 'localhost';
        $url = sprintf('http://%s:8085/%s', $goHost, $endpoint);

        try {
            $response = $this->httpClient->request(
                'POST',
                $url,
                [
                    'json' => $data,
                ]
            );
            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $responseBody = '';
            if (isset($response)) {
                try {
                    $responseBody = $response->getContent(false);
                } catch (ExceptionInterface) {
                    // getContent() still throws for transport-level failures (e.g. connection
                    // refused) even with $throw=false, which only suppresses HTTP status errors.
                }
            }

            throw new GoHttpCallException(
                sprintf(
                    'Failed to call go HTTP API: %s %s',
                    $e->getMessage(),
                    $responseBody !== '' ? 'Response: ' . $responseBody : ''
                ),
                previous: $e
            );
        }
    }

    /**
     * @throws GoHttpCallException
     */
    public function updateState(GoState $goState): void
    {
        $this->callApi('/state', (array)$goState);
    }

    /**
     * @return array<mixed>
     * @throws GoHttpCallException
     */
    public function parseBounceOrFbl(string $raw, DebugIncomingEmailType $type): array
    {
        return $this->callApi('/debug/parse-bounce-fbl', [
            'raw' => base64_encode($raw),
            'type' => $type->value,
        ]);
    }

    /**
     * @return array<mixed>
     * @throws GoHttpCallException
     */
    public function resolveDns(string $domain, DnsType $dnsType): array
    {
        return $this->callApi('/dns/resolve', [
            'name' => $domain,
            'type' => $dnsType->value,
        ]);
    }

}
