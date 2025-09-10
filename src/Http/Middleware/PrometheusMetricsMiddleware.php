<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Http\Middleware;

use Closure;
use Iamfarhad\Prometheus\Collectors\HttpRequestCollector;
use Iamfarhad\Prometheus\Traits\HasDebugLogging;
use Illuminate\Http\Request;

class PrometheusMetricsMiddleware
{
    use HasDebugLogging;

    public function __construct(private ?HttpRequestCollector $collector = null)
    {
        // Collector is optional to prevent errors when disabled
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $this->debugInfo('PrometheusMetricsMiddleware handle called');

        if (! $this->collector || ! $this->isEnabled()) {
            $this->debugInfo('Collector disabled or null - skipping metrics');
            return $next($request);
        }

        $this->debugInfo('Collector enabled, recording metrics');
        $startTime = microtime(true);

        $response = $next($request);

        $this->debugInfo('Recording HTTP request metrics');
        $this->collector->recordRequest($request, $response, $startTime);

        $this->debugInfo('PrometheusMetricsMiddleware completed');

        return $response;
    }

    private function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.http.enabled', true);
    }
}
