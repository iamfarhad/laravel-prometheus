<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Support;

use Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware;
use Illuminate\Foundation\Configuration\Middleware;

class PrometheusMiddlewareHelper
{
    /**
     * Add Prometheus metrics middleware to the application.
     *
     * This is a convenience method for easily adding the middleware
     * to Laravel 11+ applications in bootstrap/app.php.
     *
     * Note: Configuration checks are done at runtime in the middleware itself.
     */
    public static function register(Middleware $middleware): void
    {
        // Add to global middleware stack
        // The middleware itself will check if it's enabled at runtime
        $middleware->append(PrometheusMetricsMiddleware::class);
    }

    /**
     * Add Prometheus metrics middleware to specific middleware groups.
     *
     * @param  array  $groups  The middleware groups to add to (e.g., ['web', 'api'])
     */
    public static function registerToGroups(Middleware $middleware, array $groups = ['web', 'api']): void
    {
        foreach ($groups as $group) {
            $middleware->appendToGroup($group, PrometheusMetricsMiddleware::class);
        }
    }

    /**
     * Get the middleware class name for manual registration.
     */
    public static function class(): string
    {
        return PrometheusMetricsMiddleware::class;
    }
}
