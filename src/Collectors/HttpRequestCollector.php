<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Traits\HasDebugLogging;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\Summary;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class HttpRequestCollector implements CollectorInterface
{
    use HasDebugLogging;

    private ?Counter $requestCounter = null;

    private ?Histogram $requestDurationHistogram = null;

    private ?Histogram $requestSizeHistogram = null;

    private ?Histogram $responseSizeHistogram = null;

    private ?Summary $requestDurationSummary = null;

    public function __construct(private Prometheus $prometheus)
    {
        $this->debugCollectorActivity('HttpRequestCollector', 'initializing');
        $this->registerMetrics();
        $this->debugCollectorActivity('HttpRequestCollector', 'initialized');
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->requestCounter = $this->prometheus->getOrRegisterCounter(
            'http_requests_total',
            'Total number of HTTP requests',
            ['method', 'route', 'status']
        );

        $buckets = config('prometheus.collectors.http.histogram_buckets', [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]);

        $this->requestDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'route'],
            $buckets
        );

        $sizeBuckets = config('prometheus.collectors.http.size_buckets', [1024, 4096, 16384, 65536, 262144, 1048576, 4194304]);

        $this->requestSizeHistogram = $this->prometheus->getOrRegisterHistogram(
            'http_request_size_bytes',
            'HTTP request size in bytes',
            ['method', 'route'],
            $sizeBuckets
        );

        $this->responseSizeHistogram = $this->prometheus->getOrRegisterHistogram(
            'http_response_size_bytes',
            'HTTP response size in bytes',
            ['method', 'route', 'status'],
            $sizeBuckets
        );

        // Summary metric for response time percentiles (p50, p95, p99)
        $quantiles = config('prometheus.collectors.http.summary_quantiles', [0.5, 0.95, 0.99]);
        $maxAge = config('prometheus.collectors.http.summary_max_age', 600); // 10 minutes

        $this->requestDurationSummary = $this->prometheus->getOrRegisterSummary(
            'http_request_duration_seconds_summary',
            'HTTP request duration summary with quantiles',
            ['method', 'route'],
            $maxAge,
            $quantiles
        );
    }

    public function recordRequest(Request $request, mixed $response, float $startTime): void
    {
        $method = $request->method();
        $route = $this->getRouteName($request);
        $status = $this->getResponseStatus($response);

        $this->debugCollectorActivity('HttpRequestCollector', 'recording request', [
            'method' => $method,
            'route' => $route,
            'status' => $status,
        ]);

        // Record request count
        if ($this->requestCounter) {
            $this->requestCounter->inc([$method, $route, (string) $status]);
        }

        // Record request duration
        $duration = microtime(true) - $startTime;
        if ($this->requestDurationHistogram) {
            $this->requestDurationHistogram->observe($duration, [$method, $route]);
        }

        // Record request duration in summary for percentiles
        if ($this->requestDurationSummary) {
            $this->requestDurationSummary->observe($duration, [$method, $route]);
        }

        // Record request size
        if ($this->requestSizeHistogram) {
            $requestSize = $this->getRequestSize($request);
            $this->requestSizeHistogram->observe($requestSize, [$method, $route]);
        }

        // Record response size
        if ($this->responseSizeHistogram) {
            $responseSize = $this->getResponseSize($response);
            $this->responseSizeHistogram->observe($responseSize, [$method, $route, (string) $status]);
        }
    }

    protected function getRouteName(Request $request): string
    {
        $route = $request->route();

        if ($route instanceof Route) {
            return $route->getName() ?: $route->uri();
        }

        return $request->path();
    }

    protected function getResponseStatus(mixed $response): int
    {
        if ($response instanceof Response) {
            return $response->getStatusCode();
        }

        if ($response instanceof SymfonyResponse) {
            return $response->getStatusCode();
        }

        return 200; // Default fallback
    }

    protected function getRequestSize(Request $request): float
    {
        $size = 0;

        // Get content length from headers
        if ($request->headers->has('Content-Length')) {
            return (float) $request->headers->get('Content-Length');
        }

        // Calculate size from content if available
        $content = $request->getContent();
        if ($content) {
            $size += strlen($content);
        }

        // Add headers size (approximate)
        foreach ($request->headers->all() as $name => $values) {
            $size += strlen($name);
            foreach ((array) $values as $value) {
                $size += strlen($value);
            }
        }

        return (float) $size;
    }

    protected function getResponseSize(mixed $response): float
    {
        if ($response instanceof Response) {
            $content = $response->getContent();
            $size = $content ? strlen($content) : 0;

            // Add headers size (approximate)
            foreach ($response->headers->all() as $name => $values) {
                $size += strlen($name);
                foreach ((array) $values as $value) {
                    $size += strlen($value);
                }
            }

            return (float) $size;
        }

        if ($response instanceof SymfonyResponse) {
            $content = $response->getContent();
            $size = $content ? strlen($content) : 0;

            // Add headers size (approximate)
            foreach ($response->headers->all() as $name => $values) {
                $size += strlen($name);
                foreach ((array) $values as $value) {
                    $size += strlen($value);
                }
            }

            return (float) $size;
        }

        // Fallback for unknown response types
        return 0.0;
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.http.enabled', true);
    }
}
