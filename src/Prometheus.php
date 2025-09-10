<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus;

use InvalidArgumentException;
use Iamfarhad\Prometheus\Traits\HasDebugLogging;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Prometheus\Summary;

final class Prometheus
{
    use HasDebugLogging;

    public function __construct(
        private CollectorRegistry $registry,
        private string $namespace = ''
    ) {
        $this->debugInfo('Prometheus instance created', [
            'namespace' => $namespace,
            'registry_class' => get_class($registry)
        ]);
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }

    public static function createStorageAdapter(string $driver, array $config = []): Adapter
    {
        return match ($driver) {
            'redis' => self::createRedisAdapter($config),
            'memory' => new InMemory,
            'apc' => new APC,
            'apcu', 'apcng' => new APCng,
            default => throw new InvalidArgumentException("Unsupported storage driver: {$driver}"),
        };
    }

    public static function createRedisAdapter(array $config): Redis
    {
        // Use Laravel's Redis configuration
        $redisConfig = config('database.redis.default', []);

        $promRedisConfig = [
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => $redisConfig['port'] ?? 6379,
            'password' => $redisConfig['password'] ?? null,
            'timeout' => $config['timeout'] ?? 0.1,
            'read_timeout' => $config['read_timeout'] ?? '10',
            'persistent_connections' => $config['persistent_connections'] ?? false,
        ];

        Redis::setDefaultOptions($promRedisConfig);

        return new Redis($promRedisConfig);
    }

    // Counter methods
    public function registerCounter(string $name, string $help, array $labelNames = []): Counter
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->registerCounter($this->namespace, $fullName, $help, $labelNames);
    }

    public function getOrRegisterCounter(string $name, string $help, array $labelNames = []): Counter
    {
        $fullName = $this->getFullMetricName($name);

        $this->debugMetricOperation('getOrRegisterCounter', $fullName, $labelNames);

        return $this->registry->getOrRegisterCounter($this->namespace, $fullName, $help, $labelNames);
    }

    public function counter(string $name): Counter
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->getCounter($this->namespace, $fullName);
    }

    // Gauge methods
    public function registerGauge(string $name, string $help, array $labelNames = []): Gauge
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->registerGauge($this->namespace, $fullName, $help, $labelNames);
    }

    public function getOrRegisterGauge(string $name, string $help, array $labelNames = []): Gauge
    {
        $fullName = $this->getFullMetricName($name);

        $this->debugMetricOperation('getOrRegisterGauge', $fullName, $labelNames);

        return $this->registry->getOrRegisterGauge($this->namespace, $fullName, $help, $labelNames);
    }

    public function gauge(string $name): Gauge
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->getGauge($this->namespace, $fullName);
    }

    // Histogram methods
    public function registerHistogram(
        string $name,
        string $help,
        array $labelNames = [],
        ?array $buckets = null
    ): Histogram {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->registerHistogram($this->namespace, $fullName, $help, $labelNames, $buckets);
    }

    public function getOrRegisterHistogram(
        string $name,
        string $help,
        array $labelNames = [],
        ?array $buckets = null
    ): Histogram {
        $fullName = $this->getFullMetricName($name);

        $this->debugMetricOperation('getOrRegisterHistogram', $fullName, $labelNames, ['buckets' => $buckets]);

        return $this->registry->getOrRegisterHistogram($this->namespace, $fullName, $help, $labelNames, $buckets);
    }

    public function histogram(string $name): Histogram
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->getHistogram($this->namespace, $fullName);
    }

    // Summary methods
    public function registerSummary(
        string $name,
        string $help,
        array $labelNames = [],
        int $maxAgeSeconds = 600,
        ?array $quantiles = null
    ): Summary {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->registerSummary($this->namespace, $fullName, $help, $labelNames, $maxAgeSeconds, $quantiles);
    }

    public function getOrRegisterSummary(
        string $name,
        string $help,
        array $labelNames = [],
        int $maxAgeSeconds = 600,
        ?array $quantiles = null
    ): Summary {
        $fullName = $this->getFullMetricName($name);

        $this->debugMetricOperation('getOrRegisterSummary', $fullName, $labelNames, [
            'max_age_seconds' => $maxAgeSeconds,
            'quantiles' => $quantiles
        ]);

        return $this->registry->getOrRegisterSummary($this->namespace, $fullName, $help, $labelNames, $maxAgeSeconds, $quantiles);
    }

    public function summary(string $name): Summary
    {
        $fullName = $this->getFullMetricName($name);

        return $this->registry->getSummary($this->namespace, $fullName);
    }

    // Utility methods
    public function hasMetric(string $name): bool
    {
        $fullName = $this->getFullMetricName($name);
        $samples = $this->registry->getMetricFamilySamples();

        foreach ($samples as $sample) {
            if ($sample->getName() === $fullName || $sample->getName() === $this->namespace . '_' . $fullName) {
                return true;
            }
        }

        return false;
    }

    public function collect(): array
    {
        $startTime = microtime(true);
        $this->debugInfo('Starting metrics collection');

        $samples = $this->registry->getMetricFamilySamples();

        $this->debugTiming('metrics collection', $startTime, [
            'samples_count' => count($samples)
        ]);

        return $samples;
    }

    public function render(): string
    {
        $startTime = microtime(true);
        $this->debugInfo('Starting metrics rendering');

        $renderer = new RenderTextFormat;
        $result = $renderer->render($this->collect());

        $this->debugTiming('metrics rendering', $startTime, [
            'output_size' => strlen($result)
        ]);

        return $result;
    }

    public function clear(): void
    {
        // PromPHP doesn't have a clear method, but we can flush the storage adapter
        // This depends on the storage adapter implementation
        $storage = $this->registry->getStorage ?? null;
        if ($storage && method_exists($storage, 'wipeStorage')) {
            $storage->wipeStorage();
        }
    }

    private function getFullMetricName(string $name): string
    {
        // Don't add namespace prefix here since PromPHP handles namespaces differently
        return $name;
    }
}
