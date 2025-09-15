<?php

namespace App\Service\Go;

use App\Entity\Type\DebugIncomingEmailType;
use App\Service\App\Config;
use App\Service\Management\GoState\GoState;
use App\Service\Go\Exception\GoHttpCallException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
            throw new GoHttpCallException(
                sprintf(
                    'Failed to call go HTTP API: %s %s',
                    $e->getMessage(),
                    isset($response) ? 'Response: ' . $response->getContent(false) : ''
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

}
