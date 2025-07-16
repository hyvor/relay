<?php

namespace App\Tests\Case;

use App\Api\Console\Authorization\Scope;
use App\Entity\Project;
use App\Tests\Factory\ApiKeyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Auth\AuthFake;
use Hyvor\Internal\Bundle\Testing\ApiTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class WebTestCase extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{

    use ApiTestingTrait;
    use InteractsWithMessenger;

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->container = static::getContainer();

        if ($this->shouldEnableAuthFake()) {
            AuthFake::enableForSymfony($this->container, ['id' => 1]);
        }

        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $this->em = $em;
    }

    protected function shouldEnableAuthFake(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function adminApi(
        string $method,
        string $uri,
        array $data = [],
    ): Response {

        $this->client->request(
            $method,
            '/api/admin' . $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: (string)json_encode($data),
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() === 500) {
            throw new \Exception(
                'API call failed with status code 500. ' .
                'Response: ' . $response->getContent()
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $server
     * @param array<string, mixed> $parameters
     * @param true|(string|Scope)[] $scopes
     */
    public function consoleApi(
        Project|int|null $project,
        string $method,
        string $uri,
        array $data = [],
        array $parameters = [],
        array $server = [],
        true|array $scopes = true
    ): Response {
        $project = is_int($project) ? $this->em->getRepository(Project::class)->find($project) : $project;

        $apiKey = bin2hex(random_bytes(16));
        $apiKeyHashed = hash('sha256', $apiKey);
        $apiKeyFactory = ['key_hashed' => $apiKeyHashed, 'project' => $project];
        if ($scopes !== true) $apiKeyFactory['scopes'] = array_map(fn(Scope|string $scope) => is_string($scope) ? $scope : $scope->value, $scopes);
        ApiKeyFactory::createOne($apiKeyFactory);

        $this->client->request(
            $method,
            '/api/console' . $uri,
            parameters: $parameters,
            server: array_merge([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $apiKey,
            ], $server),
            content: (string)json_encode($data),
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() === 500) {
            throw new \Exception(
                'API call failed with status code 500. ' .
                'Response: ' . $response->getContent()
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $server
     */
    public function localApi(
        string $method,
        string $uri,
        array $data = [],
        array $server = [],
    ): Response {

        $this->client->request(
            $method,
            '/api/local' . $uri,
            server: array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $server),
            content: (string)json_encode($data),
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() === 500) {
            throw new \Exception(
                'API call failed with status code 500. ' .
                'Response: ' . $response->getContent()
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $server
     */
    public function sudoApi(
        string $method,
        string $uri,
        array $data = [],
        array $server = [],
    ): Response {
        $this->client->getCookieJar()->set(new Cookie('authsess', 'test-session'));

        $this->client->request(
            $method,
            '/api/sudo' . $uri,
            server: array_merge([
                'CONTENT_TYPE' => 'application/json',
            ], $server),
            content: (string)json_encode($data),
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() === 500) {
            throw new \Exception(
                'API call failed with status code 500. ' .
                'Response: ' . $response->getContent()
            );
        }

        return $response;
    }

}
