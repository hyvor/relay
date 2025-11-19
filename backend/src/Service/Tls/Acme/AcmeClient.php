<?php

namespace App\Service\Tls\Acme;

use App\Service\Tls\Acme\Dto\AccountInternalDto;
use App\Service\Tls\Acme\Dto\AuthorizationResponse\AuthorizationResponse;
use App\Service\Tls\Acme\Dto\DirectoryDto;
use App\Service\Tls\Acme\Dto\NewOrderResponse;
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

    private const string CACHE_ACCOUNT_KEY = 'acme_account';

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
            $privateKey = openssl_pkey_new([
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "private_key_bits" => 2048,
            ]);

            if ($privateKey === false) {
                throw new AcmeException('Failed to generate private key for ACME account: ' . openssl_error_string());
            }

            openssl_pkey_export($privateKey, $privateKeyPem);
            assert(is_string($privateKeyPem));

            $this->account = new AccountInternalDto(
                privateKeyPem: $privateKeyPem,
                kid: null,
            );

            return $this->newAccount();
        });
    }

    /**
     * Manages one global ACME account per Relay instance
     * @throws AcmeException
     */
    private function newAccount(): AccountInternalDto
    {
        $payload = [
            'contact' => [""],
            'termsOfServiceAgreed' => true
        ];

        $headerBag = new HeaderBag();
        $this->httpRequest($this->directory->newAccount, $payload, headerBag: $headerBag);

        $kid = $headerBag->get('location');
        if (!$kid) {
            throw new AcmeException('No KID returned from ACME server');
        }

        $this->account = new AccountInternalDto(
            privateKeyPem: $this->account->privateKeyPem,
            kid: $kid,
        );

        return $this->account;
    }

    /**
     * Orders a new certificate for the given domain
     * and returns a PendingOrder
     * Add PendingOrder->dnsRecordValue as a TXT record to the domain's DNS
     * @throws AcmeException
     */
    public function newOrder(string $domain): PendingOrder
    {
        $payload = [
            'identifiers' => [
                ['type' => 'dns', 'value' => $domain],
            ],
        ];

        $response = $this->httpRequest(
            $this->directory->newOrder,
            $payload,
            returnType: NewOrderResponse::class
        );

        $authorizationUrl = $response->firstAuthorizationUrl();
        $authorization = $this->httpRequest($authorizationUrl, returnType: AuthorizationResponse::class);

        $dnsChallenge = $authorization->getFirstDns01Challenge();
        $thumbprint = base64_encode(
            hash('sha256', (string)json_encode($this->getJwk()), true)
        );
        $keyAuthorization = $dnsChallenge->token . '.' . $thumbprint;

        // SHA256 digest, base64url-encoded without padding
        $dnsValue = rtrim(strtr(base64_encode(hash('sha256', $keyAuthorization, true)), '+/', '-_'), '=');

        return new PendingOrder(
            dnsRecordValue: $dnsValue,
            challengeUrl: $dnsChallenge->url,
            finalizeOrderUrl: $response->finalize,
        );
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
     * account->privateKey must be set before calling this method
     *
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
            $jwk = $this->getJwk();
            $protected['jwk'] = $jwk;
        }

        $protectedBase64 = $this->base64url($this->jsonEncode($protected));
        $payloadBase64 = $this->base64url($this->jsonEncode($payload));

        openssl_sign(
            $protectedBase64 . "." . $payloadBase64,
            $signature,
            $this->account->privateKeyPem,
            OPENSSL_ALGO_SHA256
        );
        assert(is_string($signature));
        $signatureBase64 = $this->base64url($signature);

        return [
            'protected' => $protectedBase64,
            'payload' => $payloadBase64,
            'signature' => $signatureBase64,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getJwk(): array
    {
        /**
         * @var array{rsa: array{n: string, e: string}} $details
         */
        $details = openssl_pkey_get_details($this->account->getPrivateKey());
        return [
            'kty' => 'RSA',
            'n' => $this->base64url($details['rsa']['n']),
            'e' => $this->base64url($details['rsa']['e']),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function jsonEncode(array $data): string
    {
        $json = json_encode($data);
        assert(is_string($json));
        return $json;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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