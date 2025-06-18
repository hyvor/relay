<?php

namespace App\Tests\Case;

use App\Entity\Project;
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
        AuthFake::enableForSymfony($this->container, ['id' => 1]);

        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $this->em = $em;
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
     * @param array<string, mixed> $parameters
     */
    public function consoleApi(
        Project|int|null $project,
        string $method,
        string $uri,
        array $data = [],
        array $parameters = [],
    ): Response {
        $projectId = $project instanceof Project ? $project->getId() : $project;

        $this->client->getCookieJar()->set(new Cookie('authsess', 'default'));
        $this->client->request(
            $method,
            '/api/console' . $uri,
            parameters: $parameters,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PROJECT_ID' => $projectId,
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

}