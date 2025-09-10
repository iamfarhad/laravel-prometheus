<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Traits;

use Illuminate\Support\Facades\Log;

trait HasDebugLogging
{
    /**
     * Log debug information if debug mode is enabled.
     */
    protected function debugLog(string $message, array $context = []): void
    {
        if ($this->isDebugEnabled()) {
            Log::debug('[Prometheus] '.$message, $context);
        }
    }

    /**
     * Log info level debug information if debug mode is enabled.
     */
    protected function debugInfo(string $message, array $context = []): void
    {
        if ($this->isDebugEnabled()) {
            Log::info('[Prometheus] '.$message, $context);
        }
    }

    /**
     * Log warning level debug information if debug mode is enabled.
     */
    protected function debugWarning(string $message, array $context = []): void
    {
        if ($this->isDebugEnabled()) {
            Log::warning('[Prometheus] '.$message, $context);
        }
    }

    /**
     * Check if debug mode is enabled.
     */
    protected function isDebugEnabled(): bool
    {
        return config('prometheus.debug', false) === true;
    }

    /**
     * Log metric operation with debug information.
     */
    protected function debugMetricOperation(string $operation, string $metricName, array $labels = [], $value = null): void
    {
        if ($this->isDebugEnabled()) {
            $context = [
                'operation' => $operation,
                'metric' => $metricName,
                'labels' => $labels,
            ];

            if ($value !== null) {
                $context['value'] = $value;
            }

            $this->debugLog("Metric operation: {$operation} on {$metricName}", $context);
        }
    }

    /**
     * Log collector activity with debug information.
     */
    protected function debugCollectorActivity(string $collector, string $action, array $data = []): void
    {
        if ($this->isDebugEnabled()) {
            $this->debugInfo("Collector {$collector}: {$action}", $data);
        }
    }

    /**
     * Log storage operation with debug information.
     */
    protected function debugStorageOperation(string $operation, array $data = []): void
    {
        if ($this->isDebugEnabled()) {
            $this->debugLog("Storage operation: {$operation}", $data);
        }
    }

    /**
     * Log performance timing information.
     */
    protected function debugTiming(string $operation, float $startTime, array $context = []): void
    {
        if ($this->isDebugEnabled()) {
            $duration = microtime(true) - $startTime;
            $context['duration_ms'] = round($duration * 1000, 2);
            $this->debugLog("Performance: {$operation} took {$context['duration_ms']}ms", $context);
        }
    }
}
