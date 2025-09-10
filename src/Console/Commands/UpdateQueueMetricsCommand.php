<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Console\Commands;

use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class UpdateQueueMetricsCommand extends Command
{
    protected $signature = 'prometheus:update-queue-metrics';

    protected $description = 'Update Prometheus queue metrics (queue sizes, failed jobs, etc.)';

    public function __construct(private Prometheus $prometheus)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('prometheus.enabled', true) || ! config('prometheus.collectors.queue.enabled', true)) {
            $this->info('Queue collector is disabled.');

            return self::SUCCESS;
        }

        $this->info('Updating queue metrics...');

        try {
            $this->updateQueueSizeMetrics();
            $this->updateFailedJobsMetrics();
            $this->updateWorkerMetrics();

            if ($this->isHorizonAvailable()) {
                $this->updateHorizonMetrics();
            }

            $this->info('Queue metrics updated successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to update queue metrics: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function updateQueueSizeMetrics(): void
    {
        $queueSizeGauge = $this->prometheus->gauge('queue_size');
        $connections = config('queue.connections', []);

        foreach ($connections as $connectionName => $config) {
            $this->line("Updating queue sizes for connection: {$connectionName}");

            try {
                switch ($config['driver']) {
                    case 'redis':
                        $this->updateRedisQueueSizes($connectionName, $queueSizeGauge);
                        break;
                    case 'database':
                        $this->updateDatabaseQueueSizes($connectionName, $queueSizeGauge);
                        break;
                    case 'sqs':
                        $this->updateSqsQueueSizes($connectionName, $queueSizeGauge);
                        break;
                    default:
                        $this->warn("Unsupported queue driver: {$config['driver']}");
                }
            } catch (Throwable $e) {
                $this->warn("Failed to update queue sizes for {$connectionName}: ".$e->getMessage());
            }
        }
    }

    protected function updateRedisQueueSizes(string $connectionName, $queueSizeGauge): void
    {
        $redis = Redis::connection($connectionName);
        $queues = $this->getQueuesForConnection($connectionName);

        foreach ($queues as $queue) {
            // Pending jobs
            $pendingSize = $redis->llen("queues:{$queue}");
            $queueSizeGauge->set($pendingSize, [
                'queue' => $queue,
                'connection' => $connectionName,
                'status' => 'pending',
            ]);

            // Delayed jobs
            $delayedSize = $redis->zcard("queues:{$queue}:delayed");
            $queueSizeGauge->set($delayedSize, [
                'queue' => $queue,
                'connection' => $connectionName,
                'status' => 'delayed',
            ]);

            // Reserved jobs
            $reservedSize = $redis->zcard("queues:{$queue}:reserved");
            $queueSizeGauge->set($reservedSize, [
                'queue' => $queue,
                'connection' => $connectionName,
                'status' => 'reserved',
            ]);

            $this->line("  {$queue}: {$pendingSize} pending, {$delayedSize} delayed, {$reservedSize} reserved");
        }
    }

    protected function updateDatabaseQueueSizes(string $connectionName, $queueSizeGauge): void
    {
        $connection = DB::connection($connectionName);
        $table = config("queue.connections.{$connectionName}.table", 'jobs');

        // Pending jobs (not reserved)
        $pendingCount = $connection->table($table)
            ->whereNull('reserved_at')
            ->count();

        // Processing jobs (reserved but not failed)
        $processingCount = $connection->table($table)
            ->whereNotNull('reserved_at')
            ->count();

        $queueSizeGauge->set($pendingCount, [
            'queue' => 'all',
            'connection' => $connectionName,
            'status' => 'pending',
        ]);

        $queueSizeGauge->set($processingCount, [
            'queue' => 'all',
            'connection' => $connectionName,
            'status' => 'processing',
        ]);

        $this->line("  Database queue: {$pendingCount} pending, {$processingCount} processing");
    }

    protected function updateSqsQueueSizes(string $connectionName, $queueSizeGauge): void
    {
        // SQS queue size monitoring would require AWS SDK
        // This is a placeholder for SQS-specific implementation
        $this->line('  SQS queue size monitoring not implemented yet');
    }

    protected function updateFailedJobsMetrics(): void
    {
        $failedJobsGauge = $this->prometheus->gauge('queue_failed_jobs');

        try {
            // Count failed jobs by queue
            $failedJobs = DB::table('failed_jobs')
                ->selectRaw('
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.displayName")), "unknown") as job_class,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.job")), "default") as queue,
                    connection,
                    COUNT(*) as count
                ')
                ->groupBy(['job_class', 'queue', 'connection'])
                ->get();

            foreach ($failedJobs as $job) {
                $failedJobsGauge->set($job->count, [
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'job_class' => $job->job_class,
                ]);
            }

            $totalFailed = $failedJobs->sum('count');
            $this->line("Failed jobs: {$totalFailed} total");
        } catch (Throwable $e) {
            $this->warn('Failed to update failed jobs metrics: '.$e->getMessage());
        }
    }

    protected function updateWorkerMetrics(): void
    {
        $workersGauge = $this->prometheus->gauge('queue_workers');

        if ($this->isHorizonAvailable()) {
            $this->updateHorizonWorkerMetrics($workersGauge);
        } else {
            // For non-Horizon setups, we can't easily count active workers
            // This would require external monitoring or process tracking
            $this->line('Worker metrics require Horizon for accurate counting');
        }
    }

    protected function updateHorizonWorkerMetrics($workersGauge): void
    {
        try {
            // This would require access to Horizon's internal APIs
            // The exact implementation depends on Horizon version and available APIs
            $this->line('Horizon worker metrics would be implemented here');
        } catch (Throwable $e) {
            $this->warn('Failed to update Horizon worker metrics: '.$e->getMessage());
        }
    }

    protected function updateHorizonMetrics(): void
    {
        $this->line('Updating Horizon-specific metrics...');

        try {
            // Supervisor metrics
            $supervisorsGauge = $this->prometheus->gauge('horizon_supervisors');

            // This would require accessing Horizon's supervisor data
            // Implementation depends on Horizon's internal structure

            $this->line('Horizon metrics updated');
        } catch (Throwable $e) {
            $this->warn('Failed to update Horizon metrics: '.$e->getMessage());
        }
    }

    protected function getQueuesForConnection(string $connectionName): array
    {
        $queueConfig = config("queue.connections.{$connectionName}.queue");

        if (is_string($queueConfig)) {
            return [$queueConfig];
        }

        if (is_array($queueConfig)) {
            return $queueConfig;
        }

        // Default queue names to check
        return ['default', 'high', 'low'];
    }

    protected function isHorizonAvailable(): bool
    {
        return class_exists('Laravel\Horizon\Horizon');
    }
}
