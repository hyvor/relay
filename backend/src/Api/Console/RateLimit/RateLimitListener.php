<?php

namespace App\Api\Console\RateLimit;

use App\Api\Console\Authorization\AuthorizationListener;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

// priority less than AuthorizationListener
// more than IdempotencyListener
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController', priority: 150)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
class RateLimitListener
{

    public function __construct(
        private LockFactory $lockFactory,
        private CacheItemPoolInterface $cacheItemPool,
        private RateLimit $rateLimit,
    )
    {
    }

    private const string RATE_LIMIT_HEADERS_ATTRIBUTE_KEY = 'console_api_rate_limit_headers';

    private function isConsoleApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/console');
    }

    private function getRateLimiter(Request $request): LimiterInterface
    {

        // check if this is a session request (user logged in)
        if (AuthorizationListener::hasUser($request)) {
            $user = AuthorizationListener::getUser($request);
            return $this->createRateLimiter($this->rateLimit->session(), "user:" . $user->id);
        }

        //  otherwise, it is an API request with a project
        $apiKey = AuthorizationListener::getApiKey($request);

        return $this->createRateLimiter($this->rateLimit->apiKey(), 'api_key:' . $apiKey->getId());

    }

    /**
     * @param array<string, mixed> $config
     */
    private function createRateLimiter(array $config, string $key): LimiterInterface
    {
        $storage = new CacheStorage($this->cacheItemPool);
        $rateLimiterFactory = new RateLimiterFactory($config, $storage, $this->lockFactory);
        return $rateLimiterFactory->create($key);
    }

    public function onController(ControllerEvent $controllerEvent): void
    {
        if ($controllerEvent->isMainRequest() === false) return;

        $request = $controllerEvent->getRequest();
        if (!$this->isConsoleApiRequest($request)) return;

        $limiter = $this->getRateLimiter($request);
        $limit = $limiter->consume();

        $resetIn = $limit->getRetryAfter()->getTimestamp() - time();
        $request->attributes->set(self::RATE_LIMIT_HEADERS_ATTRIBUTE_KEY, [
            'X-RateLimit-Limit' => $limit->getLimit(),
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => $resetIn,
        ]);

        if ($limit->isAccepted() === false) {
            throw new TooManyRequestsHttpException(
                message: 'Rate limit exceeded. Please try again later in ' . $resetIn . ' seconds.',
            );
        }
    }

    public function onResponse(ResponseEvent $responseEvent): void
    {
        if ($responseEvent->isMainRequest() === false) return;

        $request = $responseEvent->getRequest();
        if (!$this->isConsoleApiRequest($request)) return;

        $response = $responseEvent->getResponse();

        if ($request->attributes->has(self::RATE_LIMIT_HEADERS_ATTRIBUTE_KEY)) {
            /** @var array<string, string|int> $rateLimitHeaders */
            $rateLimitHeaders = $request->attributes->get(self::RATE_LIMIT_HEADERS_ATTRIBUTE_KEY);
            foreach ($rateLimitHeaders as $header => $value) {
                $response->headers->set($header, (string) $value);
            }
        }
    }

}