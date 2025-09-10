<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Facades;

use Iamfarhad\Prometheus\Prometheus as PrometheusClass;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Iamfarhad\Prometheus\Metrics\Counter registerCounter(string $name, string $help, array $labelNames = [])
 * @method static \Iamfarhad\Prometheus\Metrics\Counter counter(string $name)
 * @method static \Iamfarhad\Prometheus\Metrics\Gauge registerGauge(string $name, string $help, array $labelNames = [])
 * @method static \Iamfarhad\Prometheus\Metrics\Gauge gauge(string $name)
 * @method static \Iamfarhad\Prometheus\Metrics\Histogram registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = null)
 * @method static \Iamfarhad\Prometheus\Metrics\Histogram histogram(string $name)
 * @method static \Iamfarhad\Prometheus\Metrics\Summary registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = null)
 * @method static \Iamfarhad\Prometheus\Metrics\Summary summary(string $name)
 * @method static bool hasMetric(string $name)
 * @method static array collect()
 * @method static string render()
 * @method static void clear()
 * @method static void setNamespace(string $namespace)
 * @method static string getNamespace()
 */
class Prometheus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PrometheusClass::class;
    }
}
