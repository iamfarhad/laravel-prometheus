<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Feature;

use Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class HttpMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear metrics to avoid conflicts between tests
        $prometheus = $this->app->make(Prometheus::class);
        $prometheus->clear();

        // Set up test routes with middleware
        Route::middleware(PrometheusMetricsMiddleware::class)->group(function () {
            Route::get('/test', function () {
                return response('OK', 200);
            })->name('test.route');

            Route::get('/test-error', function () {
                return response('Error', 500);
            })->name('test.error');

            Route::post('/test-post', function () {
                return response('OK', 200);
            });
        });
    }

    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
        $response->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_http_metrics_are_collected(): void
    {
        // Manually trigger HTTP metrics collection
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $prometheus = $this->app->make(Prometheus::class);

        // Simulate a request being recorded
        $request = \Illuminate\Http\Request::create('/test', 'GET');
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/test', function () {});
            $route->name('test.route');

            return $route;
        });

        $response = new \Illuminate\Http\Response('OK', 200);
        $startTime = microtime(true) - 0.1; // Simulate 100ms request

        $collector->recordRequest($request, $response, $startTime);

        // Get the metrics
        $output = $prometheus->render();

        // Check that HTTP metrics are present
        $this->assertStringContainsString('http_requests_total', $output);
        $this->assertStringContainsString('http_request_duration_seconds', $output);
    }

    public function test_http_metrics_track_different_status_codes(): void
    {
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $prometheus = $this->app->make(Prometheus::class);

        // Simulate requests with different status codes
        $request200 = \Illuminate\Http\Request::create('/test', 'GET');
        $request200->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/test', function () {});
            $route->name('test.route');

            return $route;
        });

        $request500 = \Illuminate\Http\Request::create('/test-error', 'GET');
        $request500->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/test-error', function () {});
            $route->name('test.error');

            return $route;
        });

        $response200 = new \Illuminate\Http\Response('OK', 200);
        $response500 = new \Illuminate\Http\Response('Error', 500);
        $startTime = microtime(true) - 0.1;

        $collector->recordRequest($request200, $response200, $startTime);
        $collector->recordRequest($request500, $response500, $startTime);

        $output = $prometheus->render();

        // Check that different status codes are tracked
        $this->assertStringContainsString('status="200"', $output);
        $this->assertStringContainsString('status="500"', $output);
    }

    public function test_http_collector_can_be_instantiated(): void
    {
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $prometheus = $this->app->make(Prometheus::class);

        $this->assertTrue($collector->isEnabled());
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class, $collector);
        $this->assertInstanceOf(Prometheus::class, $prometheus);
    }

    public function test_http_collector_records_different_methods(): void
    {
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $prometheus = $this->app->make(Prometheus::class);

        // Simulate GET request
        $getRequest = \Illuminate\Http\Request::create('/test', 'GET');
        $getRequest->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/test', function () {});
            $route->name('test.route');

            return $route;
        });

        // Simulate POST request
        $postRequest = \Illuminate\Http\Request::create('/test', 'POST');
        $postRequest->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['POST'], '/test', function () {});
            $route->name('test.route');

            return $route;
        });

        $response = new \Illuminate\Http\Response('OK', 200);
        $startTime = microtime(true) - 0.1;

        $collector->recordRequest($getRequest, $response, $startTime);
        $collector->recordRequest($postRequest, $response, $startTime);

        $output = $prometheus->render();

        $this->assertStringContainsString('method="GET"', $output);
        $this->assertStringContainsString('method="POST"', $output);
    }

    public function test_http_collector_can_record_requests(): void
    {
        // Ensure HTTP collector is enabled
        config(['prometheus.collectors.http.enabled' => true]);

        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $prometheus = $this->app->make(Prometheus::class);

        $request = \Illuminate\Http\Request::create('/test', 'GET');
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/test', function () {});
            $route->name('test.route');

            return $route;
        });

        $response = new \Illuminate\Http\Response('OK', 200);
        $startTime = microtime(true) - 0.1;

        // Record a request - this should not throw an exception
        $collector->recordRequest($request, $response, $startTime);

        // Verify we can render metrics without errors
        $output = $prometheus->render();
        $this->assertIsString($output);
        $this->assertStringContainsString('http_requests_total', $output);
    }
}
