<?php

namespace App\Api\Console\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::TERMINATE)]
class MetricsListener
{
    private const string NAMESPACE = 'app_api';
    private CollectorRegistry $registry;
    private Counter $requestsTotal;

    public function __construct(
        private PrometheusFactory $prometheusFactory,
        private RouterInterface $router,
    )
    {
        $this->registry = $this->prometheusFactory->createRegistry();
        $this->requestsTotal = $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'http_requests_total',
            'Total number of HTTP requests',
            ['method', 'endpoint', 'status']
        );
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/console')) {
            return;
        }

        if ($event->isMainRequest() === false) {
            return;
        }

        $response = $event->getResponse();

        $this->requestsTotal->inc(
            [
                $request->getMethod(),
                $this->getEndpoint($request),
                (string)$response->getStatusCode(),
            ]
        );
    }

    private function getEndpoint(Request $request): string
    {
        $routeName = $request->attributes->get('_route');

        if (!$routeName) {
            return '/<unknown>';
        }

        $route = $this->router->getRouteCollection()->get($routeName);

        return $route instanceof Route ? $route->getPath() : '/<unknown>';
    }

    public function getSamples(): mixed
    {
        return $this->registry->getMetricFamilySamples();
    }
}
