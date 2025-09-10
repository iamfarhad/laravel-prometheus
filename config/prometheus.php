<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Prometheus Package Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the Prometheus package is enabled.
    | When disabled, all collectors will be inactive and no metrics
    | will be collected.
    |
    */

    'enabled' => env('PROMETHEUS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Global Namespace
    |--------------------------------------------------------------------------
    |
    | The global namespace prefix for all metrics. This helps to avoid
    | metric name conflicts when multiple applications are monitored
    | by the same Prometheus instance.
    |
    | Example: 'myapp' will result in metrics like 'myapp_http_requests_total'
    |
    */

    'namespace' => env('PROMETHEUS_NAMESPACE', ''),

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled, the package will log detailed information
    | about metric collection, storage operations, and collector activity.
    | This is useful for troubleshooting but should be disabled in production.
    |
    */

    'debug' => env('PROMETHEUS_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how metrics data is stored. Available drivers:
    | - redis: Production-ready, persistent storage using Redis
    | - memory: For testing and development (data lost on restart)
    | - apcu: For single-process environments (limited scalability)
    |
    */

    'storage' => [
        'driver' => env('PROMETHEUS_STORAGE_DRIVER', 'redis'),

        'redis' => [
            'connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
            'prefix' => env('PROMETHEUS_REDIS_PREFIX', 'metrics_'),
        ],

        'apcu' => [
            'prefix' => env('PROMETHEUS_APCU_PREFIX', 'prometheus_'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic middleware registration. When auto_register is true,
    | the package will attempt to automatically register the HTTP collector
    | middleware without requiring manual addition to bootstrap/app.php.
    |
    */

    'middleware' => [
        'auto_register' => env('PROMETHEUS_MIDDLEWARE_AUTO_REGISTER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the /metrics endpoint for Prometheus scraping.
    | Set 'enabled' to false if you want to handle the route manually.
    |
    */

    'metrics_route' => [
        'enabled' => env('PROMETHEUS_METRICS_ROUTE_ENABLED', true),
        'path' => env('PROMETHEUS_METRICS_PATH', '/metrics'),
        'middleware' => env('PROMETHEUS_METRICS_MIDDLEWARE', []),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings for the metrics endpoint.
    | Use the AllowIps middleware to restrict access to specific IP addresses.
    |
    */

    'security' => [
        'allowed_ips' => env('PROMETHEUS_ALLOWED_IPS') ? explode(',', env('PROMETHEUS_ALLOWED_IPS')) : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Collectors Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/disable built-in collectors. Each collector can be toggled
    | individually to minimize performance impact.
    |
    */

    'collectors' => [
        'http' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_HTTP_ENABLED', true),

            // Duration tracking (response time) - Industry standard for SLO monitoring
            // Covers: p50 (~100ms), p95 (~500ms), p99 (~1s), p99.9 (~2.5s) SLOs
            'histogram_buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
            'summary_quantiles' => [0.5, 0.95, 0.99, 0.999],
            'summary_max_age' => 600, // 10 minutes

            // Size tracking (request/response bytes) - Exponential spacing for wide range
            // Covers: small payloads (1KB) to large API responses (16MB)
            'size_buckets' => [1024, 4096, 16384, 65536, 262144, 1048576, 4194304, 16777216],
        ],

        'database' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_DATABASE_ENABLED', true),

            // Query duration tracking - Fine-grained for database performance monitoring
            // Covers: fast queries (~1ms) to slow queries (~5s), aligns with common DB SLOs
            'histogram_buckets' => [0.0005, 0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
            'summary_quantiles' => [0.5, 0.95, 0.99, 0.999],
            'summary_max_age' => 600, // 10 minutes
        ],

        'cache' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_CACHE_ENABLED', false),

            // Cache operation duration tracking - Sub-millisecond precision for cache operations
            // Covers: memory cache (~0.1ms) to distributed cache (~100ms)
            'histogram_buckets' => [0.0001, 0.0005, 0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1],
            'summary_quantiles' => [0.5, 0.95, 0.99],
            'summary_max_age' => 300, // 5 minutes (cache operations are frequent)
        ],

        'queue' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_QUEUE_ENABLED', true),

            // Job processing duration tracking - Wide range for different job types
            // Covers: quick jobs (~100ms) to long-running jobs (~10min)
            'histogram_buckets' => [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 300.0, 600.0],
            'summary_quantiles' => [0.5, 0.95, 0.99],
            'summary_max_age' => 900, // 15 minutes (jobs can be long-running)
        ],

        'events' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_EVENTS_ENABLED', false),
            'histogram_buckets' => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        ],

        'errors' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_ERRORS_ENABLED', true),
        ],

        'filesystem' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_FILESYSTEM_ENABLED', false),
            'disks' => ['local', 'public'], // Which disks to monitor
            'histogram_buckets' => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        ],

        'mail' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_MAIL_ENABLED', false),
            'histogram_buckets' => [0.1, 0.25, 0.5, 1.0, 2.0, 5.0, 10.0, 30.0, 60.0, 120.0],
        ],

        'notification' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_NOTIFICATION_ENABLED', false),
            'histogram_buckets' => [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        ],

        'command' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_COMMAND_ENABLED', true),
            // Artisan command duration - Wide range for different command types
            // Covers: quick commands (~100ms) to migrations/seeds (~30min)
            'histogram_buckets' => [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 300.0, 600.0, 1800.0],
        ],

        'horizon' => [
            'enabled' => env('PROMETHEUS_COLLECTOR_HORIZON_ENABLED', false),
            'update_interval' => env('PROMETHEUS_HORIZON_UPDATE_INTERVAL', 60), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Histogram Buckets
    |--------------------------------------------------------------------------
    |
    | Default bucket configuration for histograms. These are used when
    | no custom buckets are specified for a histogram metric.
    |
    | Industry standard buckets optimized for SLO monitoring:
    | - Covers typical SLO ranges (p50, p95, p99, p99.9)
    | - Exponential spacing for efficient resource usage
    | - 11 buckets (recommended optimal count)
    |
    */

    'default_histogram_buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],

    /*
    |--------------------------------------------------------------------------
    | Default Summary Quantiles
    |--------------------------------------------------------------------------
    |
    | Default quantile configuration for summaries. These are used when
    | no custom quantiles are specified for a summary metric.
    |
    | Industry standard quantiles for SLO monitoring:
    | - 0.5 (median/p50): Shows typical performance
    | - 0.95 (p95): Common SLO threshold (95% of requests)
    | - 0.99 (p99): Tail latency monitoring (99% of requests)
    | - 0.999 (p99.9): Critical tail latency for high-scale systems
    |
    */

    'default_summary_quantiles' => [0.5, 0.95, 0.99, 0.999],

    /*
    |--------------------------------------------------------------------------
    | Default Summary Max Age
    |--------------------------------------------------------------------------
    |
    | Default max age for summary metrics in seconds. This determines how
    | long observations are kept for quantile calculation. Shorter periods
    | provide more recent data but less statistical accuracy.
    |
    */

    'default_summary_max_age' => 600, // 10 minutes
];
