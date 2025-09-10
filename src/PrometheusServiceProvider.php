<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus;

use Iamfarhad\Prometheus\Collectors\CacheOperationCollector;
use Iamfarhad\Prometheus\Collectors\CommandCollector;
use Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector;
use Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector;
use Iamfarhad\Prometheus\Collectors\ErrorCollector;
use Iamfarhad\Prometheus\Collectors\EventCollector;
use Iamfarhad\Prometheus\Collectors\FileSystemCollector;
use Iamfarhad\Prometheus\Collectors\HorizonCollector;
use Iamfarhad\Prometheus\Collectors\HttpRequestCollector;
use Iamfarhad\Prometheus\Collectors\MailCollector;
use Iamfarhad\Prometheus\Collectors\QueueJobCollector;
use Iamfarhad\Prometheus\Console\Commands\UpdateQueueMetricsCommand;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;

class PrometheusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/prometheus.php' => config_path('prometheus.php'),
        ], 'prometheus-config');

        $this->mergeConfigFrom(__DIR__.'/../config/prometheus.php', 'prometheus');

        // Register routes if enabled
        if ($this->app['config']->get('prometheus.metrics_route.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }

        // Register HTTP middleware if auto-registration is enabled
        $this->registerHttpMiddleware();

        // Boot enabled collectors
        $this->bootCollectors();

        // Register console commands
        $this->registerCommands();
    }

    public function register(): void
    {
        $this->registerStorageAdapter();
        $this->registerPrometheus();
        $this->registerFacade();
        $this->registerCollectors();
    }

    protected function registerStorageAdapter(): void
    {
        $this->app->singleton(Adapter::class, function ($app) {
            $driver = $app['config']->get('prometheus.storage.driver', 'memory');
            $config = $app['config']->get('prometheus.storage', []);

            return Prometheus::createStorageAdapter($driver, $config);
        });
    }

    protected function registerPrometheus(): void
    {
        $this->app->singleton(CollectorRegistry::class, function ($app) {
            $storage = $app->make(Adapter::class);

            return new CollectorRegistry($storage);
        });

        $this->app->singleton(Prometheus::class, function ($app) {
            $registry = $app->make(CollectorRegistry::class);
            $namespace = $app['config']->get('prometheus.namespace', '');

            return new Prometheus($registry, $namespace);
        });
    }

    protected function registerFacade(): void
    {
        $this->app->singleton('prometheus', function ($app) {
            return $app->make(Prometheus::class);
        });
    }

    protected function registerCollectors(): void
    {
        if (! $this->app['config']->get('prometheus.enabled', true)) {
            return;
        }

        $collectors = $this->app['config']->get('prometheus.collectors', []);

        if ($collectors['http']['enabled'] ?? false) {
            $this->app->singleton(HttpRequestCollector::class);
        }

        if ($collectors['database']['enabled'] ?? false) {
            $this->app->singleton(DatabaseQueryCollector::class);
        }

        if ($collectors['cache']['enabled'] ?? false) {
            $this->app->singleton(CacheOperationCollector::class);
        }

        if ($collectors['queue']['enabled'] ?? false) {
            $this->app->singleton(QueueJobCollector::class);
        }

        if ($collectors['events']['enabled'] ?? false) {
            $this->app->singleton(EventCollector::class);
        }

        if ($collectors['errors']['enabled'] ?? false) {
            $this->app->singleton(ErrorCollector::class);
        }

        if ($collectors['filesystem']['enabled'] ?? false) {
            $this->app->singleton(FileSystemCollector::class);
        }

        if ($collectors['mail']['enabled'] ?? false) {
            $this->app->singleton(MailCollector::class);
        }

        if ($collectors['command']['enabled'] ?? false) {
            $this->app->singleton(CommandCollector::class);
        }

        // Register enhanced queue collector (optional, alternative to basic queue collector)
        if ($collectors['queue']['enhanced'] ?? false) {
            $this->app->singleton(EnhancedQueueJobCollector::class);
        }

        // Register Horizon collector
        if ($collectors['horizon']['enabled'] ?? false) {
            $this->app->singleton(HorizonCollector::class);
        }
    }

    protected function bootCollectors(): void
    {
        if (! $this->app['config']->get('prometheus.enabled', true)) {
            return;
        }

        $collectors = $this->app['config']->get('prometheus.collectors', []);

        // Instantiate enabled collectors to activate their event listeners
        if ($collectors['database']['enabled'] ?? false) {
            $this->app->make(DatabaseQueryCollector::class);
        }

        if ($collectors['cache']['enabled'] ?? false) {
            $this->app->make(CacheOperationCollector::class);
        }

        if ($collectors['queue']['enabled'] ?? false) {
            $this->app->make(QueueJobCollector::class);
        }

        if ($collectors['events']['enabled'] ?? false) {
            $this->app->make(EventCollector::class);
        }

        if ($collectors['errors']['enabled'] ?? false) {
            $this->app->make(ErrorCollector::class);
        }

        if ($collectors['filesystem']['enabled'] ?? false) {
            $this->app->make(FileSystemCollector::class);
        }

        if ($collectors['mail']['enabled'] ?? false) {
            $this->app->make(MailCollector::class);
        }

        if ($collectors['command']['enabled'] ?? false) {
            $this->app->make(CommandCollector::class);
        }

        // Boot enhanced queue collector if enabled
        if ($collectors['queue']['enhanced'] ?? false) {
            $this->app->make(EnhancedQueueJobCollector::class);
        }

        // Boot Horizon collector if enabled
        if ($collectors['horizon']['enabled'] ?? false) {
            $this->app->make(HorizonCollector::class);
        }
    }

    protected function registerHttpMiddleware(): void
    {
        // Only register if Prometheus is enabled and HTTP collector is enabled
        if (! $this->app['config']->get('prometheus.enabled', true)) {
            return;
        }

        $httpCollectorEnabled = $this->app['config']->get('prometheus.collectors.http.enabled', true);
        $autoRegisterMiddleware = $this->app['config']->get('prometheus.middleware.auto_register', false);

        if (! $httpCollectorEnabled || ! $autoRegisterMiddleware) {
            return;
        }

        // Try to register middleware automatically for Laravel applications
        $this->app->booted(function () {
            try {
                // For Laravel 11+ applications using the new bootstrap structure
                if ($this->app->bound('router')) {
                    $router = $this->app->make('router');

                    // Add to web and api middleware groups if they exist
                    if (method_exists($router, 'getMiddlewareGroups')) {
                        $groups = $router->getMiddlewareGroups();

                        if (isset($groups['web']) && method_exists($router, 'pushMiddlewareToGroup')) {
                            $router->pushMiddlewareToGroup('web', \Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class);
                        }

                        if (isset($groups['api']) && method_exists($router, 'pushMiddlewareToGroup')) {
                            $router->pushMiddlewareToGroup('api', \Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fail if automatic registration doesn't work
                // Users can still register manually
                if (config('prometheus.debug', false)) {
                    \Log::warning('[Prometheus] Automatic middleware registration failed: '.$e->getMessage());
                }
            }
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateQueueMetricsCommand::class,
            ]);
        }
    }
}
