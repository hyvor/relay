<?php

namespace App\Service\PrivateNetwork;

use App\Entity\Type\DebugIncomingEmailsType;
use App\Service\Management\GoState\GoState;
use App\Service\PrivateNetwork\Exception\GoHttpCallException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoHttpApi
{

    public function __construct(
        private HttpClientInterface $httpClient,

        // usually only needed in DEV where Go is not running on localhost
        #[Autowire('%env(GO_HOST)%')]
        private ?string $goHost = null
    )
    {
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws GoHttpCallException
     */
    private function callApi(string $endpoint, array $data): array
    {

        $endpoint = trim($endpoint, '/');

        $goHost = $this->goHost ?? 'localhost';
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
        $this->callApi('/state', (array) $goState);
    }

    /**
     * @throws GoHttpCallException
     * @return array<mixed>
     */
    public function parseBounceOrFbl(string $raw, DebugIncomingEmailsType $type): array
    {
        return $this->callApi('/debug/parse-bounce-fbl', [
            'raw' => base64_encode($raw),
            'type' => $type->value,
        ]);
    }

}