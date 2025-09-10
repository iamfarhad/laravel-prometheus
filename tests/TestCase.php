<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests;

use Iamfarhad\Prometheus\PrometheusServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('prometheus.enabled', true);
        $this->app['config']->set('prometheus.storage.driver', 'memory');
        $this->app['config']->set('prometheus.namespace', 'test');
    }

    protected function getPackageProviders($app): array
    {
        return [
            PrometheusServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('prometheus.enabled', true);
        $app['config']->set('prometheus.storage.driver', 'memory');
        $app['config']->set('prometheus.collectors.http.enabled', true);
        $app['config']->set('prometheus.collectors.database.enabled', true);
        $app['config']->set('prometheus.collectors.cache.enabled', true);
        $app['config']->set('prometheus.collectors.queue.enabled', true);
    }
}
