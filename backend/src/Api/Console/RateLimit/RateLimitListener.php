<?php

namespace App\Api\Console\RateLimit;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\App\RateLimit\RateLimiterProvider;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\AccessType;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleApiAuthorizationListenerAbstract;
use Hyvor\Internal\CloudApi\ConsoleApiAuth\ConsoleAuthResults;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;

// priority less than AuthorizationListener
// more than IdempotencyListener
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController', priority: 150)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
class RateLimitListener
{

    public function __construct(
        private RateLimit $rateLimit,
        private RateLimiterProvider $rateLimiterProvider,
    ) {
    }

    private const string RATE_LIMIT_HEADERS_ATTRIBUTE_KEY = 'console_api_rate_limit_headers';

    private function isConsoleApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/console');
    }

    private function getRateLimiter(Request $request): LimiterInterface
    {
        $consoleAuthResults = $request->attributes->get(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY);
        assert($consoleAuthResults instanceof ConsoleAuthResults);

        // check if this is a session request (user logged in)
        if ($consoleAuthResults->getAccessType() === AccessType::SESSION) {
            $user = $consoleAuthResults->getNullableUser();
            assert($user !== null);
            return $this->rateLimiterProvider->rateLimiter($this->rateLimit->session(), "user:" . $user->id);
        }

        //  otherwise, it is an API request with a project
        $project = $consoleAuthResults->getResource();
        assert($project instanceof Project);

        // special limit for the POST /sends endpoint
        if ($request->getMethod() === 'POST' && $request->getPathInfo() === '/api/console/sends') {
            return $this->rateLimiterProvider->rateLimiter(
                $this->rateLimit->sends(),
                'sends:project:' . $project->getId()
            );
        }

        if ($consoleAuthResults->getAccessType() === AccessType::PRODUCT_API_KEY) {
            $apiKey = $consoleAuthResults->getResource();
            return $this->rateLimiterProvider->rateLimiter($this->rateLimit->apiKey(), 'api_key:project:' . $project->getId());
        }

        return $this->rateLimiterProvider->rateLimiter($this->rateLimit->apiKey(), 'cloud:project:' . $project->getId());
    }

    public function onController(ControllerEvent $controllerEvent): void
    {
        if ($controllerEvent->isMainRequest() === false) {
            return; // @codeCoverageIgnore
        }

        $request = $controllerEvent->getRequest();
        if (!$this->isConsoleApiRequest($request)) {
            return; // @codeCoverageIgnore
        }

        if (!$request->attributes->has(ConsoleApiAuthorizationListenerAbstract::ATTRIBUTE_KEY)) {
            return;
        }

        $limiter = $this->getRateLimiter($request);
        $limit = $limiter->consume();

        $resetIn = max($limit->getRetryAfter()->getTimestamp() - time(), 0);
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
        if ($responseEvent->isMainRequest() === false) {
            return; // @codeCoverageIgnore
        }

        $request = $responseEvent->getRequest();
        if (!$this->isConsoleApiRequest($request)) {
            return; // @codeCoverageIgnore
        }

        $response = $responseEvent->getResponse();

        if ($request->attributes->has(self::RATE_LIMIT_HEADERS_ATTRIBUTE_KEY)) {
            /** @var array<string, string|int> $rateLimitHeaders */
            $rateLimitHeaders = $request->attributes->get(self::RATE_LIMIT_HEADERS_ATTRIBUTE_KEY);
            foreach ($rateLimitHeaders as $header => $value) {
                $response->headers->set($header, (string)$value);
            }
        }
    }

}
