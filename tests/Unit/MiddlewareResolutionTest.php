<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Unit;

use Iamfarhad\Prometheus\Collectors\HttpRequestCollector;
use Iamfarhad\Prometheus\Http\Middleware\AllowIps;
use Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware;
use Iamfarhad\Prometheus\Tests\TestCase;

final class MiddlewareResolutionTest extends TestCase
{
    public function test_allow_ips_middleware_can_be_auto_resolved(): void
    {
        // Laravel should be able to auto-resolve AllowIps middleware
        // since it has no constructor dependencies
        $middleware = $this->app->make(AllowIps::class);

        $this->assertInstanceOf(AllowIps::class, $middleware);
    }

    public function test_prometheus_metrics_middleware_can_be_auto_resolved(): void
    {
        // Laravel should be able to auto-resolve PrometheusMetricsMiddleware
        // even though it has an optional HttpRequestCollector dependency
        $middleware = $this->app->make(PrometheusMetricsMiddleware::class);

        $this->assertInstanceOf(PrometheusMetricsMiddleware::class, $middleware);
    }

    public function test_prometheus_metrics_middleware_gets_collector_dependency(): void
    {
        // When HttpRequestCollector is available in container,
        // it should be injected into PrometheusMetricsMiddleware

        // First register the collector in the container
        $this->app->singleton(HttpRequestCollector::class, function ($app) {
            return new HttpRequestCollector($app->make('prometheus'));
        });

        $middleware = $this->app->make(PrometheusMetricsMiddleware::class);

        $this->assertInstanceOf(PrometheusMetricsMiddleware::class, $middleware);

        // Use reflection to check if collector was injected
        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('collector');
        $property->setAccessible(true);
        $collector = $property->getValue($middleware);

        $this->assertInstanceOf(HttpRequestCollector::class, $collector);
    }

    public function test_middleware_works_without_explicit_container_binding(): void
    {
        // Test that middleware can be resolved multiple times
        // (confirming it's not bound as singleton)

        $middleware1 = $this->app->make(AllowIps::class);
        $middleware2 = $this->app->make(AllowIps::class);

        // These should be different instances (not singletons)
        $this->assertNotSame($middleware1, $middleware2);
        $this->assertInstanceOf(AllowIps::class, $middleware1);
        $this->assertInstanceOf(AllowIps::class, $middleware2);
    }
}
