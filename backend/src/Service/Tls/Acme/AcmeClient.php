<?php

namespace App\Service\Tls\Acme;

use App\Service\Tls\Acme\Dto\AccountInternalDto;
use App\Service\Tls\Acme\Dto\DirectoryDto;
use App\Service\Tls\Acme\Exception\AcmeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AcmeClient
{

    public const string DIRECTORY_URL_LETSENCRYPT_PRODUCTION = 'https://acme-v02.api.letsencrypt.org/directory';
    public const string DIRECTORY_URL_LETSENCRYPT_STAGING = 'https://acme-staging-v02.api.letsencrypt.org/directory';

    public const string CACHE_ACCOUNT_KEY = 'acme_account';

    private string $directoryUrl;
    private DirectoryDto $directory;
    private AccountInternalDto $account;
    private ?string $nonce = null;

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        private CacheInterface $cache,
        private DenormalizerInterface $denormalizer,
        #[Autowire('%kernel.environment%')]
        private string $env,
    ) {
        if ($this->env === 'prod') {
            $this->directoryUrl = self::DIRECTORY_URL_LETSENCRYPT_PRODUCTION;
        } else {
            $this->directoryUrl = self::DIRECTORY_URL_LETSENCRYPT_STAGING;
        }
    }

    /**
     * @throws AcmeException
     */
    public function init(): void
    {
        $this->loadDirectory();
        $this->loadAccount();
    }

    /**
     * @throws AcmeException
     */
    private function loadDirectory(): void
    {
        if (isset($this->directory)) {
            return;
        }

        $this->directory = $this->httpRequest($this->directoryUrl, returnType: DirectoryDto::class, method: 'GET');
    }

    private function loadAccount(): void
    {
        $this->account = $this->cache->get(self::CACHE_ACCOUNT_KEY, function () {
            $this->account = new AccountInternalDto(
                privateKeyEncrypted: '',
                kid: null,
            );
            //
        });
    }

    /**
     * @throws AcmeException
     */
    private function newAccount(): void
    {
        $payload = [
            'contact' => [""],
            'termsOfServiceAgreed' => true
        ];

        $resp = $this->httpRequest($this->directory->newAccount, $payload);
    }

    /**
     * @param class-string<T> $returnType
     * @param array<string, mixed> $payload
     * @param 'POST'|'GET'|'HEAD' $method
     * @return T
     * @throws AcmeException
     * @template T of object
     */
    private function httpRequest(
        string $url,
        array $payload = [],
        string $returnType = \stdClass::class,
        string $method = 'POST',
        ?HeaderBag $headerBag = null, // will capture headers
    ): object
    {
        try {
            $response = $this->http->request(
                $method,
                $url,
                $method === 'POST' ? ['json' => $this->sign($payload, $url)] : [],
            );

            $body = $response->toArray();
            /** @var T $object */
            $object = $this->denormalizer->denormalize($body, $returnType);

            if ($headerBag !== null) {
                foreach ($response->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        $headerBag->set($name, $value);
                    }
                }
            }

            return $object;
        } catch (ExceptionInterface $e) {
            $this->logger->error('HTTP request to ACME server failed', [
                'url' => $url,
                'payload' => $payload,
                'exception' => $e->getMessage(),
            ]);

            throw new AcmeException('HTTP request failed: ' . $e->getMessage());
        } catch (\Symfony\Component\Serializer\Exception\ExceptionInterface $e) {
            $this->logger->error('Failed to deserialize ACME server response', [
                'url' => $url,
                'payload' => $payload,
                'exception' => $e->getMessage(),
            ]);

            throw new AcmeException('Deserialization failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws AcmeException
     */
    private function sign(array $payload, string $url): array
    {
        if (!$this->nonce) {
            $this->fetchNonce();
        }

        $protected = [
            'alg' => 'RS256',
            'nonce' => $this->nonce,
            'url' => $url,
        ];

        if ($this->account->kid) {
            $protected['kid'] = $this->account->kid;
        } else {
        }

        if ($useKid && $this->kid) {
            $protected['kid'] = $this->kid;
        } else {
            $jwk = $this->getJwk();
            $protected['jwk'] = $jwk;
        }

        $protected_b64 = $this->base64url(json_encode($protected));
        $payload_b64 = $this->base64url(json_encode($payload));

        openssl_sign($protected_b64 . "." . $payload_b64, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signature_b64 = $this->base64url($signature);

        return [
            'protected' => $protected_b64,
            'payload' => $payload_b64,
            'signature' => $signature_b64,
        ];
    }

    private function getJwk(): array
    {
        $details = openssl_pkey_get_details($this->privateKey);
        return [
            'kty' => 'RSA',
            'n' => $this->base64url($details['rsa']['n']),
            'e' => $this->base64url($details['rsa']['e']),
        ];
    }

    /**
     * @throws AcmeException
     */
    private function fetchNonce(): void
    {
        $headers = new HeaderBag();
        $this->httpRequest(
            $this->directory->newNonce,
            method: 'HEAD',
            headerBag: $headers
        );

        $nonce = $headers->get('replay-nonce');

        if (!$nonce) {
            throw new AcmeException('No nonce returned from ACME server');
        }

        $this->nonce = $nonce;
    }

}