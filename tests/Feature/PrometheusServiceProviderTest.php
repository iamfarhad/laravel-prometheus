<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Feature;

use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Tests\TestCase;
use Prometheus\CollectorRegistry;

final class PrometheusServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_collector_registry(): void
    {
        $registry = $this->app->make(CollectorRegistry::class);

        $this->assertInstanceOf(CollectorRegistry::class, $registry);
    }

    public function test_service_provider_registers_prometheus(): void
    {
        $prometheus = $this->app->make(Prometheus::class);

        $this->assertInstanceOf(Prometheus::class, $prometheus);
    }

    public function test_service_provider_registers_facade(): void
    {
        // Test that the Prometheus facade alias is registered
        $this->assertTrue($this->app->bound('prometheus'));

        $prometheus = $this->app->make('prometheus');
        $this->assertInstanceOf(Prometheus::class, $prometheus);
    }

    public function test_prometheus_is_singleton(): void
    {
        $prometheus1 = $this->app->make(Prometheus::class);
        $prometheus2 = $this->app->make(Prometheus::class);

        $this->assertSame($prometheus1, $prometheus2);
    }

    public function test_collector_registry_is_singleton(): void
    {
        $registry1 = $this->app->make(CollectorRegistry::class);
        $registry2 = $this->app->make(CollectorRegistry::class);

        $this->assertSame($registry1, $registry2);
    }

    public function test_config_is_merged(): void
    {
        $config = $this->app['config'];

        $this->assertTrue($config->has('prometheus'));
        $this->assertTrue($config->get('prometheus.enabled'));
        $this->assertEquals('memory', $config->get('prometheus.storage.driver'));
        $this->assertEquals('test', $config->get('prometheus.namespace'));
    }

    public function test_routes_are_loaded_when_enabled(): void
    {
        $this->app['config']->set('prometheus.routes.enabled', true);

        // Note: PromPHP doesn't have a clear method like our old implementation

        // Re-register the service provider to apply config changes
        $provider = new \Iamfarhad\Prometheus\PrometheusServiceProvider($this->app);
        $provider->boot();

        $routes = $this->app['router']->getRoutes();
        $metricsRoute = $routes->getByName('prometheus.metrics');

        $this->assertNotNull($metricsRoute);
        $this->assertEquals('metrics', $metricsRoute->uri());
    }

    public function test_collectors_are_registered_when_enabled(): void
    {
        // Note: PromPHP doesn't have a clear method like our old implementation

        // Verify that collectors are properly configured
        $this->assertTrue($this->app['config']->get('prometheus.collectors.http.enabled'));
        $this->assertTrue($this->app['config']->get('prometheus.collectors.database.enabled'));
        $this->assertTrue($this->app['config']->get('prometheus.collectors.cache.enabled'));
        $this->assertTrue($this->app['config']->get('prometheus.collectors.queue.enabled'));

        // Verify they can be resolved (which implicitly tests binding)
        $httpCollector = $this->app->make(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class, $httpCollector);

        $databaseCollector = $this->app->make(\Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector::class, $databaseCollector);

        $cacheCollector = $this->app->make(\Iamfarhad\Prometheus\Collectors\CacheOperationCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\CacheOperationCollector::class, $cacheCollector);

        $queueCollector = $this->app->make(\Iamfarhad\Prometheus\Collectors\QueueJobCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\QueueJobCollector::class, $queueCollector);
    }

    public function test_collectors_are_not_registered_when_prometheus_disabled(): void
    {
        // Create a new app instance with prometheus disabled
        $app = $this->createApplication();
        $app['config']->set('prometheus.enabled', false);

        $provider = new \Iamfarhad\Prometheus\PrometheusServiceProvider($app);
        $provider->register();

        // Collectors should not be registered when prometheus is disabled
        $this->assertFalse($app->bound(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class));
        $this->assertFalse($app->bound(\Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector::class));
        $this->assertFalse($app->bound(\Iamfarhad\Prometheus\Collectors\CacheOperationCollector::class));
        $this->assertFalse($app->bound(\Iamfarhad\Prometheus\Collectors\QueueJobCollector::class));
    }

    public function test_individual_collectors_can_be_disabled(): void
    {
        // Test with HTTP collector disabled
        $this->app['config']->set('prometheus.collectors.http.enabled', false);

        // Re-register service provider
        $provider = new \Iamfarhad\Prometheus\PrometheusServiceProvider($this->app);
        $provider->register();

        // HTTP collector should not be bound, others should be
        $this->assertFalse($this->app->bound(\Iamfarhad\Prometheus\Collectors\HttpRequestCollector::class));

        // Other collectors should still be bound
        $this->assertTrue($this->app->bound(\Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector::class));
        $this->assertTrue($this->app->bound(\Iamfarhad\Prometheus\Collectors\CacheOperationCollector::class));
        $this->assertTrue($this->app->bound(\Iamfarhad\Prometheus\Collectors\QueueJobCollector::class));
    }
}
