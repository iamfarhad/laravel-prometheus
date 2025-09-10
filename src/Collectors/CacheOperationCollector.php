<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Traits\HasDebugLogging;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\Summary;

final class CacheOperationCollector implements CollectorInterface
{
    use HasDebugLogging;

    private ?Counter $cacheOperationCounter = null;

    private ?Histogram $cacheOperationDurationHistogram = null;

    private ?Summary $cacheOperationDurationSummary = null;

    public function __construct(private Prometheus $prometheus)
    {
        $this->registerMetrics();
        $this->registerEventListeners();
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->cacheOperationCounter = $this->prometheus->getOrRegisterCounter(
            'cache_operations_total',
            'Total number of cache operations',
            ['store', 'operation', 'result']
        );

        $buckets = config('prometheus.collectors.cache.histogram_buckets', [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0]);

        $this->cacheOperationDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'cache_operation_duration_seconds',
            'Cache operation duration in seconds',
            ['store', 'operation'],
            $buckets
        );

        // Summary metric for cache operation time percentiles
        $quantiles = config('prometheus.collectors.cache.summary_quantiles', [0.5, 0.95, 0.99]);
        $maxAge = config('prometheus.collectors.cache.summary_max_age', 600); // 10 minutes

        $this->cacheOperationDurationSummary = $this->prometheus->getOrRegisterSummary(
            'cache_operation_duration_seconds_summary',
            'Cache operation duration summary with quantiles',
            ['store', 'operation'],
            $maxAge,
            $quantiles
        );
    }

    protected function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Listen to cache events
        Event::listen(CacheHit::class, [$this, 'handleCacheHit']);
        Event::listen(CacheMissed::class, [$this, 'handleCacheMiss']);
        Event::listen(KeyWritten::class, [$this, 'handleKeyWritten']);
        Event::listen(KeyForgotten::class, [$this, 'handleKeyForgotten']);
    }

    public function handleCacheHit(CacheHit $event): void
    {
        // Only track application cache operations, not internal storage operations
        if (! $this->shouldTrackCacheOperation($event)) {
            return;
        }

        $startTime = microtime(true);
        $this->recordOperation('get', 'hit', $event->storeName ?? 'default');
        $this->recordDuration('get', $event->storeName ?? 'default', microtime(true) - $startTime);
    }

    public function handleCacheMiss(CacheMissed $event): void
    {
        // Only track application cache operations, not internal storage operations
        if (! $this->shouldTrackCacheOperation($event)) {
            return;
        }

        $this->debugCollectorActivity('CacheOperationCollector', 'cache miss tracked', [
            'key' => $event->key ?? '',
            'store' => $event->storeName ?? 'default',
            'tags' => $event->tags ?? [],
        ]);

        $startTime = microtime(true);
        $this->recordOperation('get', 'miss', $event->storeName ?? 'default');
        $this->recordDuration('get', $event->storeName ?? 'default', microtime(true) - $startTime);
    }

    public function handleKeyWritten(KeyWritten $event): void
    {
        // Only track application cache operations, not internal storage operations
        if (! $this->shouldTrackCacheOperation($event)) {
            return;
        }

        $startTime = microtime(true);
        $this->recordOperation('put', 'success', $event->storeName ?? 'default');
        $this->recordDuration('put', $event->storeName ?? 'default', microtime(true) - $startTime);
    }

    public function handleKeyForgotten(KeyForgotten $event): void
    {
        // Only track application cache operations, not internal storage operations
        if (! $this->shouldTrackCacheOperation($event)) {
            return;
        }

        $startTime = microtime(true);
        $this->recordOperation('forget', 'success', $event->storeName ?? 'default');
        $this->recordDuration('forget', $event->storeName ?? 'default', microtime(true) - $startTime);
    }

    protected function recordOperation(string $operation, string $result, string $store): void
    {
        // Record operation count
        if ($this->cacheOperationCounter) {
            $this->cacheOperationCounter->inc([$store, $operation, $result]);
        }
    }

    public function recordOperationWithDuration(string $operation, string $store, float $duration, string $result = 'success'): void
    {
        // Record operation count
        if ($this->cacheOperationCounter) {
            $this->cacheOperationCounter->inc([$store, $operation, $result]);
        }

        // Record operation duration
        if ($this->cacheOperationDurationHistogram) {
            $this->cacheOperationDurationHistogram->observe($duration, [$store, $operation]);
        }
    }

    protected function recordDuration(string $operation, string $store, float $duration): void
    {
        // Record operation duration
        if ($this->cacheOperationDurationHistogram) {
            $this->cacheOperationDurationHistogram->observe($duration, [$store, $operation]);
        }

        // Record operation duration in summary for percentiles
        if ($this->cacheOperationDurationSummary) {
            $this->cacheOperationDurationSummary->observe($duration, [$store, $operation]);
        }
    }

    /**
     * Determine if we should track a cache operation based on its characteristics.
     * We only want to track application-level cache operations, not internal storage.
     */
    protected function shouldTrackCacheOperation($event): bool
    {
        $storeName = $event->storeName ?? 'default';
        $key = $event->key ?? '';

        // Only track cache operations from the application cache store (not default Redis)
        // Default Redis is often used for sessions, queues, and internal storage
        if ($storeName === 'default') {
            return false;
        }

        // Skip Prometheus-related cache operations to avoid recursive counting
        if ($this->isPrometheusOperation($key)) {
            return false;
        }

        // Skip Laravel internal cache keys
        if (
            str_starts_with($key, 'laravel_session:') ||
            str_starts_with($key, 'laravel_cache:') ||
            str_contains($key, 'laravel_queue:') ||
            str_contains($key, 'telescope:') ||
            str_contains($key, 'horizon:')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Check if a cache key is related to Prometheus operations.
     * This prevents recursive counting when Prometheus reads its own metrics from Redis.
     */
    protected function isPrometheusOperation(string $key): bool
    {
        $prometheusPrefix = config('prometheus.storage.redis.prefix', 'prometheus_');

        return str_starts_with($key, $prometheusPrefix) ||
            // Check for the actual keys being used by PromPHP
            str_contains($key, '_prometheus_') ||
            str_contains($key, 'prometheus_counter:') ||
            str_contains($key, 'prometheus_histogram:') ||
            str_contains($key, 'prometheus_summary:') ||
            str_contains($key, 'prometheus_gauge:') ||
            // Check for Laravel cache prefix with prometheus
            str_starts_with($key, 'laravel_database_prometheus_') ||
            str_contains($key, ':prometheus:') ||
            str_contains($key, 'PROMETHEUS_') ||
            // Check for PromPHP specific metric suffixes
            str_contains($key, '_bucket') ||
            str_contains($key, '_total') ||
            str_contains($key, '_count') ||
            str_contains($key, '_sum') ||
            // Check for specific metric names that are Prometheus-related
            str_contains($key, 'http_requests_total') ||
            str_contains($key, 'database_queries_total') ||
            str_contains($key, 'cache_operations_total') ||
            str_contains($key, 'queue_jobs_total') ||
            str_contains($key, 'artisan_commands_total');
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.cache.enabled', true);
    }
}
