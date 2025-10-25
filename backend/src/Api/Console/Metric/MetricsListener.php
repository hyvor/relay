<?php

namespace App\Api\Console\Metric;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\MetricFamilySamples;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::TERMINATE)]
class MetricsListener
{
    private CollectorRegistry $registry;
    private Counter $requestsTotal;

    public function __construct(
        private PrometheusFactory $prometheusFactory,
        private RouterInterface $router,
    ) {
        $this->registry = $this->prometheusFactory->createRegistry();
        $this->requestsTotal = $this->registry->getOrRegisterCounter(
            '',
            'http_requests_total',
            'Total number of HTTP requests',
            ['method', 'endpoint', 'status']
        );
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        // @codeCoverageIgnoreStart
        if ($event->isMainRequest() === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

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

        // @codeCoverageIgnoreStart
        if (!is_string($routeName)) {
            return '/<unknown>';
        }
        // @codeCoverageIgnoreEnd

        $route = $this->router->getRouteCollection()->get($routeName);

        return $route instanceof Route ? $route->getPath() : '/<unknown>';
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function getSamples(): array
    {
        return $this->registry->getMetricFamilySamples();
    }
}
