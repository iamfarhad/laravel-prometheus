<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Support\Facades\Storage;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Throwable;

final class FileSystemCollector implements CollectorInterface
{
    private ?Counter $filesystemOperationsCounter = null;

    private ?Gauge $diskUsageGauge = null;

    private ?Histogram $fileSizeHistogram = null;

    private ?Histogram $operationDurationHistogram = null;

    public function __construct(private Prometheus $prometheus)
    {
        $this->registerMetrics();
        $this->schedulePeriodicMetrics();
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->filesystemOperationsCounter = $this->prometheus->getOrRegisterCounter(
            'filesystem_operations_total',
            'Total number of filesystem operations',
            ['disk', 'operation', 'status', 'path_prefix']
        );

        $this->diskUsageGauge = $this->prometheus->getOrRegisterGauge(
            'filesystem_disk_usage_bytes',
            'Current disk space usage in bytes',
            ['disk', 'type']
        );

        $this->fileSizeHistogram = $this->prometheus->getOrRegisterHistogram(
            'filesystem_file_size_bytes',
            'Distribution of file sizes in bytes',
            ['disk', 'operation', 'path_prefix'],
            [1024, 10240, 102400, 1048576, 10485760, 104857600, 1073741824, 10737418240] // 1KB to 10GB
        );

        $this->operationDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'filesystem_operation_duration_seconds',
            'Time spent on filesystem operations',
            ['disk', 'operation'],
            [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
        );
    }

    public function isEnabled(): bool
    {
        return config('prometheus.collectors.filesystem.enabled', false);
    }

    public function recordFileOperation(
        string $operation,
        string $disk,
        string $path,
        ?int $fileSize = null,
        bool $success = true,
        ?float $duration = null
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $pathPrefix = $this->getPathPrefix($path);
            $status = $success ? 'success' : 'failure';

            // Record operation count
            if ($this->filesystemOperationsCounter) {
                $this->filesystemOperationsCounter->inc([
                    'disk' => $disk,
                    'operation' => $operation,
                    'status' => $status,
                    'path_prefix' => $pathPrefix,
                ]);
            }

            // Record file size distribution
            if ($fileSize !== null && $this->fileSizeHistogram && in_array($operation, ['read', 'write', 'upload'])) {
                $this->fileSizeHistogram->observe($fileSize, [
                    'disk' => $disk,
                    'operation' => $operation,
                    'path_prefix' => $pathPrefix,
                ]);
            }

            // Record operation duration
            if ($duration !== null && $this->operationDurationHistogram) {
                $this->operationDurationHistogram->observe($duration, [
                    'disk' => $disk,
                    'operation' => $operation,
                ]);
            }
        } catch (Throwable $e) {
            // Don't let metrics collection break the application
            report($e);
        }
    }

    public function recordDiskUsage(): void
    {
        if (! $this->isEnabled() || ! $this->diskUsageGauge) {
            return;
        }

        try {
            $disks = config('prometheus.collectors.filesystem.disks', ['local', 'public']);

            foreach ($disks as $diskName) {
                $this->recordDiskUsageForDisk($diskName);
            }

            // Also record system disk usage
            $this->recordSystemDiskUsage();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function recordDiskUsageForDisk(string $diskName): void
    {
        try {
            $disk = Storage::disk($diskName);

            // Try to get disk usage if supported
            if (method_exists($disk, 'size')) {
                $totalSize = $this->calculateTotalDiskSize($disk);
                $usedSize = $this->calculateUsedDiskSize($disk);
                $freeSize = $totalSize - $usedSize;

                if ($totalSize > 0 && $this->diskUsageGauge) {
                    $this->diskUsageGauge->set($totalSize, ['disk' => $diskName, 'type' => 'total']);
                    $this->diskUsageGauge->set($usedSize, ['disk' => $diskName, 'type' => 'used']);
                    $this->diskUsageGauge->set($freeSize, ['disk' => $diskName, 'type' => 'free']);
                }
            }
        } catch (Throwable $e) {
            // Skip disks that don't support size operations
        }
    }

    private function recordSystemDiskUsage(): void
    {
        try {
            $storagePath = storage_path();

            if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
                $totalBytes = disk_total_space($storagePath);
                $freeBytes = disk_free_space($storagePath);
                $usedBytes = $totalBytes - $freeBytes;

                if ($totalBytes !== false && $freeBytes !== false && $this->diskUsageGauge) {
                    $this->diskUsageGauge->set($totalBytes, ['disk' => 'system', 'type' => 'total']);
                    $this->diskUsageGauge->set($usedBytes, ['disk' => 'system', 'type' => 'used']);
                    $this->diskUsageGauge->set($freeBytes, ['disk' => 'system', 'type' => 'free']);
                }
            }
        } catch (Throwable $e) {
            // System disk usage not available
        }
    }

    private function calculateTotalDiskSize(mixed $disk): int
    {
        try {
            // This is a simplified calculation - in practice you might want to implement
            // more sophisticated disk size calculation based on the storage driver
            return 0; // Placeholder - implementation depends on storage driver
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function calculateUsedDiskSize(mixed $disk): int
    {
        try {
            $totalSize = 0;
            $files = $disk->allFiles();

            foreach ($files as $file) {
                try {
                    $totalSize += $disk->size($file);
                } catch (Throwable $e) {
                    // Skip files that can't be accessed
                    continue;
                }
            }

            return $totalSize;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function getPathPrefix(string $path): string
    {
        // Handle empty path
        if (empty(trim($path, '/'))) {
            return 'root';
        }

        // Extract the first directory level as path prefix
        $parts = explode('/', trim($path, '/'));
        $prefix = $parts[0] ?? 'root';

        // Limit to reasonable length and sanitize
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix) ?? $prefix;

        return substr($sanitized, 0, 20);
    }

    private function schedulePeriodicMetrics(): void
    {
        // In a real implementation, you might want to set up a scheduled task
        // or use Laravel's scheduler to periodically update disk usage metrics
        // For now, we'll update on each request if enough time has passed

        static $lastUpdate = 0;
        $now = time();

        // Update disk usage every 5 minutes
        if ($now - $lastUpdate > 300) {
            $this->recordDiskUsage();
            $lastUpdate = $now;
        }
    }
}
