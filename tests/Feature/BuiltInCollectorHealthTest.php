<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Feature;

use Iamfarhad\Prometheus\Collectors\CacheOperationCollector;
use Iamfarhad\Prometheus\Collectors\CommandCollector;
use Iamfarhad\Prometheus\Collectors\DatabaseQueryCollector;
use Iamfarhad\Prometheus\Collectors\ErrorCollector;
use Iamfarhad\Prometheus\Collectors\EventCollector;
use Iamfarhad\Prometheus\Collectors\FileSystemCollector;
use Iamfarhad\Prometheus\Collectors\HorizonCollector;
use Iamfarhad\Prometheus\Collectors\HttpRequestCollector;
use Iamfarhad\Prometheus\Collectors\MailCollector;
use Iamfarhad\Prometheus\Collectors\QueueJobCollector;
use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Tests\TestCase;

final class BuiltInCollectorHealthTest extends TestCase
{
    /**
     * @return array<string, class-string<CollectorInterface>>
     */
    private function builtInCollectors(): array
    {
        return [
            'http' => HttpRequestCollector::class,
            'database' => DatabaseQueryCollector::class,
            'cache' => CacheOperationCollector::class,
            'queue' => QueueJobCollector::class,
            'events' => EventCollector::class,
            'errors' => ErrorCollector::class,
            'filesystem' => FileSystemCollector::class,
            'mail' => MailCollector::class,
            'command' => CommandCollector::class,
            'horizon' => HorizonCollector::class,
        ];
    }

    public function test_all_package_configured_built_in_collectors_have_implementations(): void
    {
        $packageConfig = require __DIR__.'/../../config/prometheus.php';
        $configuredCollectors = array_keys($packageConfig['collectors']);
        $implementedCollectors = array_keys($this->builtInCollectors());

        sort($configuredCollectors);
        sort($implementedCollectors);

        $this->assertSame($implementedCollectors, $configuredCollectors);
    }

    public function test_enabled_built_in_collectors_can_be_resolved(): void
    {
        foreach ($this->builtInCollectors() as $configKey => $collectorClass) {
            config(["prometheus.collectors.{$configKey}.enabled" => true]);

            $collector = $this->app->make($collectorClass);

            $this->assertInstanceOf(CollectorInterface::class, $collector);
        }
    }

    public function test_disabled_built_in_collectors_report_unhealthy(): void
    {
        foreach ($this->builtInCollectors() as $configKey => $collectorClass) {
            config(["prometheus.collectors.{$configKey}.enabled" => false]);

            $collector = $this->app->make($collectorClass);

            $this->assertFalse(
                $collector->isEnabled(),
                "Expected {$collectorClass} to be disabled when prometheus.collectors.{$configKey}.enabled is false."
            );
        }
    }
}
