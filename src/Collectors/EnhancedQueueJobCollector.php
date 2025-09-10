<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Throwable;

final class EnhancedQueueJobCollector implements CollectorInterface
{
    // Job processing metrics
    private ?Counter $queueJobCounter = null;

    private ?Histogram $queueJobDurationHistogram = null;

    private ?Gauge $queueActiveJobsGauge = null;

    private ?Gauge $queueWaitTimeGauge = null;

    private ?Counter $queueJobRetriesCounter = null;

    private ?Counter $queueJobTimeoutsCounter = null;

    // Queue size and depth metrics
    private ?Gauge $queueSizeGauge = null;

    private ?Gauge $queuePendingJobsGauge = null;

    private ?Gauge $queueFailedJobsGauge = null;

    // Worker metrics
    private ?Gauge $queueWorkersGauge = null;

    private ?Counter $workerRestartCounter = null;

    // Horizon-specific metrics (if available)
    private ?Gauge $horizonSupervisorsGauge = null;

    private ?Gauge $horizonWorkloadGauge = null;

    private ?Counter $horizonMasterLoopsCounter = null;

    private array $jobStartTimes = [];

    private array $jobQueuedTimes = [];

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

        $buckets = config('prometheus.collectors.queue.histogram_buckets', [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0]);

        // Core job processing metrics
        $this->queueJobCounter = $this->prometheus->getOrRegisterCounter(
            'queue_jobs_total',
            'Total number of queue jobs processed',
            ['queue', 'connection', 'status', 'job_class']
        );

        $this->queueJobDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'queue_job_duration_seconds',
            'Queue job processing duration in seconds',
            ['queue', 'job_class', 'status'],
            $buckets
        );

        $this->queueActiveJobsGauge = $this->prometheus->getOrRegisterGauge(
            'queue_active_jobs',
            'Current number of active jobs being processed',
            ['queue', 'connection']
        );

        $this->queueWaitTimeGauge = $this->prometheus->getOrRegisterGauge(
            'queue_job_wait_time_seconds',
            'Time jobs wait in queue before processing',
            ['queue', 'job_class']
        );

        // Retry and timeout metrics
        $this->queueJobRetriesCounter = $this->prometheus->getOrRegisterCounter(
            'queue_job_retries_total',
            'Total number of job retries',
            ['queue', 'job_class', 'reason']
        );

        $this->queueJobTimeoutsCounter = $this->prometheus->getOrRegisterCounter(
            'queue_job_timeouts_total',
            'Total number of job timeouts',
            ['queue', 'job_class']
        );

        // Queue size metrics
        $this->queueSizeGauge = $this->prometheus->getOrRegisterGauge(
            'queue_size',
            'Current number of jobs in each queue',
            ['queue', 'connection', 'status']
        );

        $this->queuePendingJobsGauge = $this->prometheus->getOrRegisterGauge(
            'queue_pending_jobs',
            'Number of pending jobs waiting to be processed',
            ['queue', 'connection']
        );

        $this->queueFailedJobsGauge = $this->prometheus->getOrRegisterGauge(
            'queue_failed_jobs',
            'Number of failed jobs in the failed jobs table',
            ['queue', 'connection']
        );

        // Worker metrics
        $this->queueWorkersGauge = $this->prometheus->getOrRegisterGauge(
            'queue_workers',
            'Number of active queue workers',
            ['queue', 'connection', 'supervisor']
        );

        $this->workerRestartCounter = $this->prometheus->getOrRegisterCounter(
            'queue_worker_restarts_total',
            'Total number of worker restarts',
            ['connection', 'reason']
        );

        // Horizon-specific metrics (if Horizon is available)
        if ($this->isHorizonAvailable()) {
            $this->horizonSupervisorsGauge = $this->prometheus->getOrRegisterGauge(
                'horizon_supervisors',
                'Number of active Horizon supervisors',
                ['environment']
            );

            $this->horizonWorkloadGauge = $this->prometheus->getOrRegisterGauge(
                'horizon_workload',
                'Current Horizon workload distribution',
                ['queue', 'supervisor']
            );

            $this->horizonMasterLoopsCounter = $this->prometheus->getOrRegisterCounter(
                'horizon_master_loops_total',
                'Total number of Horizon master supervisor loops',
                ['environment']
            );
        }
    }

    protected function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Core Laravel queue events
        Event::listen(JobQueued::class, [$this, 'handleJobQueued']);
        Event::listen(JobProcessing::class, [$this, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [$this, 'handleJobProcessed']);
        Event::listen(JobFailed::class, [$this, 'handleJobFailed']);
        Event::listen(JobExceptionOccurred::class, [$this, 'handleJobExceptionOccurred']);
        Event::listen(JobRetryRequested::class, [$this, 'handleJobRetryRequested']);
        Event::listen(JobTimedOut::class, [$this, 'handleJobTimedOut']);
        Event::listen(WorkerStopping::class, [$this, 'handleWorkerStopping']);

        // Register Horizon events if available
        if ($this->isHorizonAvailable()) {
            $this->registerHorizonEventListeners();
        }

        // Schedule periodic metrics collection
        $this->schedulePeriodicMetrics();
    }

    protected function registerHorizonEventListeners(): void
    {
        // Try to register Horizon-specific events
        $horizonEvents = [
            'Laravel\Horizon\Events\MasterSupervisorLooped',
            'Laravel\Horizon\Events\SupervisorLooped',
            'Laravel\Horizon\Events\WorkerProcessRestarting',
            'Laravel\Horizon\Events\JobDeleted',
            'Laravel\Horizon\Events\JobPushed',
            'Laravel\Horizon\Events\JobReserved',
            'Laravel\Horizon\Events\JobReleased',
        ];

        foreach ($horizonEvents as $eventClass) {
            if (class_exists($eventClass)) {
                $methodName = 'handle'.class_basename($eventClass);
                if (method_exists($this, $methodName)) {
                    Event::listen($eventClass, [$this, $methodName]);
                }
            }
        }
    }

    public function handleJobQueued(JobQueued $event): void
    {
        $jobId = $this->getJobId($event->job);
        $this->jobQueuedTimes[$jobId] = microtime(true);

        // Update pending jobs gauge
        if ($this->queuePendingJobsGauge) {
            $this->queuePendingJobsGauge->inc([
                'queue' => $this->getJobQueue($event->job),
                'connection' => $event->connectionName,
            ]);
        }
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $this->getJobId($event->job);
        $queue = $this->getJobQueue($event->job);
        $jobClass = $this->getJobClass($event->job);

        $this->jobStartTimes[$jobId] = microtime(true);

        // Calculate wait time if we have queued time
        if (isset($this->jobQueuedTimes[$jobId]) && $this->queueWaitTimeGauge) {
            $waitTime = microtime(true) - $this->jobQueuedTimes[$jobId];
            $this->queueWaitTimeGauge->set($waitTime, [
                'queue' => $queue,
                'job_class' => $jobClass,
            ]);
            unset($this->jobQueuedTimes[$jobId]);
        }

        // Increment active jobs and decrement pending
        if ($this->queueActiveJobsGauge) {
            $this->queueActiveJobsGauge->inc([
                'queue' => $queue,
                'connection' => $event->connectionName,
            ]);
        }

        if ($this->queuePendingJobsGauge) {
            $this->queuePendingJobsGauge->dec([
                'queue' => $queue,
                'connection' => $event->connectionName,
            ]);
        }
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->recordJobCompletion($event->job, $event->connectionName, 'completed');
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->recordJobCompletion($event->job, $event->connectionName, 'failed');

        // Update failed jobs gauge
        if ($this->queueFailedJobsGauge) {
            $this->queueFailedJobsGauge->inc([
                'queue' => $this->getJobQueue($event->job),
                'connection' => $event->connectionName,
            ]);
        }
    }

    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->recordJobCompletion($event->job, $event->connectionName, 'exception');
    }

    public function handleJobRetryRequested(JobRetryRequested $event): void
    {
        if ($this->queueJobRetriesCounter) {
            $this->queueJobRetriesCounter->inc([
                'queue' => $this->getJobQueue($event->job),
                'job_class' => $this->getJobClass($event->job),
                'reason' => 'retry_requested',
            ]);
        }
    }

    public function handleJobTimedOut(JobTimedOut $event): void
    {
        if ($this->queueJobTimeoutsCounter) {
            $this->queueJobTimeoutsCounter->inc([
                'queue' => $this->getJobQueue($event->job),
                'job_class' => $this->getJobClass($event->job),
            ]);
        }

        $this->recordJobCompletion($event->job, $event->connectionName, 'timeout');
    }

    public function handleWorkerStopping(WorkerStopping $event): void
    {
        if ($this->workerRestartCounter) {
            $this->workerRestartCounter->inc([
                'connection' => $event->connectionName ?? 'unknown',
                'reason' => $event->status ?? 'unknown',
            ]);
        }
    }

    // Horizon event handlers
    public function handleMasterSupervisorLooped($event): void
    {
        if ($this->horizonMasterLoopsCounter) {
            $this->horizonMasterLoopsCounter->inc([
                'environment' => app()->environment(),
            ]);
        }
    }

    public function handleSupervisorLooped($event): void
    {
        // Update supervisor and workload metrics if available
        if (isset($event->supervisor) && $this->horizonWorkloadGauge) {
            // This would need more sophisticated logic to extract workload data
            // depending on the actual event structure
        }
    }

    protected function recordJobCompletion(mixed $job, string $connectionName, string $status): void
    {
        $jobId = $this->getJobId($job);
        $queue = $this->getJobQueue($job);
        $jobClass = $this->getJobClass($job);

        // Record job count
        if ($this->queueJobCounter) {
            $this->queueJobCounter->inc([
                'queue' => $queue,
                'connection' => $connectionName,
                'status' => $status,
                'job_class' => $jobClass,
            ]);
        }

        // Record job duration
        if (isset($this->jobStartTimes[$jobId]) && $this->queueJobDurationHistogram) {
            $duration = microtime(true) - $this->jobStartTimes[$jobId];
            $this->queueJobDurationHistogram->observe($duration, [
                'queue' => $queue,
                'job_class' => $jobClass,
                'status' => $status,
            ]);
            unset($this->jobStartTimes[$jobId]);
        }

        // Decrement active jobs gauge
        if ($this->queueActiveJobsGauge) {
            $this->queueActiveJobsGauge->dec([
                'queue' => $queue,
                'connection' => $connectionName,
            ]);
        }

        // Clean up queued time if it exists
        unset($this->jobQueuedTimes[$jobId]);
    }

    protected function schedulePeriodicMetrics(): void
    {
        // This would typically be called by a scheduled command
        // to update queue size metrics periodically
        if (app()->runningInConsole()) {
            try {
                $this->updateQueueSizeMetrics();
            } catch (Throwable $e) {
                // Log error but don't break the application
                logger()->warning('Failed to update queue size metrics: '.$e->getMessage());
            }
        }
    }

    protected function updateQueueSizeMetrics(): void
    {
        if (! $this->queueSizeGauge) {
            return;
        }

        // Get queue configurations
        $connections = config('queue.connections', []);

        foreach ($connections as $connectionName => $config) {
            if ($config['driver'] === 'redis') {
                $this->updateRedisQueueSizes($connectionName);
            } elseif ($config['driver'] === 'database') {
                $this->updateDatabaseQueueSizes($connectionName);
            }
        }
    }

    protected function updateRedisQueueSizes(string $connectionName): void
    {
        try {
            $redis = app('redis')->connection($connectionName);
            $queues = config("queue.connections.{$connectionName}.queue", 'default');

            if (is_string($queues)) {
                $queues = [$queues];
            }

            foreach ($queues as $queue) {
                $size = $redis->llen("queues:{$queue}");
                $this->queueSizeGauge->set($size, [
                    'queue' => $queue,
                    'connection' => $connectionName,
                    'status' => 'pending',
                ]);
            }
        } catch (Throwable $e) {
            logger()->warning("Failed to get Redis queue sizes for {$connectionName}: ".$e->getMessage());
        }
    }

    protected function updateDatabaseQueueSizes(string $connectionName): void
    {
        try {
            $db = app('db')->connection($connectionName);
            $table = config("queue.connections.{$connectionName}.table", 'jobs');

            $pendingCount = $db->table($table)->whereNull('reserved_at')->count();
            $processingCount = $db->table($table)->whereNotNull('reserved_at')->count();

            $this->queueSizeGauge->set($pendingCount, [
                'queue' => 'all',
                'connection' => $connectionName,
                'status' => 'pending',
            ]);

            $this->queueSizeGauge->set($processingCount, [
                'queue' => 'all',
                'connection' => $connectionName,
                'status' => 'processing',
            ]);
        } catch (Throwable $e) {
            logger()->warning("Failed to get database queue sizes for {$connectionName}: ".$e->getMessage());
        }
    }

    protected function getJobId(mixed $job): string
    {
        // Try to get a unique job identifier
        if (method_exists($job, 'getJobId')) {
            return $job->getJobId();
        }

        if (isset($job->uuid)) {
            return $job->uuid;
        }

        if (property_exists($job, 'job') && isset($job->job->uuid)) {
            return $job->job->uuid;
        }

        // Fallback to a hash of the job object
        return md5(spl_object_hash($job).microtime());
    }

    protected function getJobQueue(mixed $job): string
    {
        if (method_exists($job, 'getQueue')) {
            return $job->getQueue() ?: 'default';
        }

        if (property_exists($job, 'queue')) {
            return $job->queue ?: 'default';
        }

        return 'default';
    }

    protected function getJobClass(mixed $job): string
    {
        return get_class($job);
    }

    protected function isHorizonAvailable(): bool
    {
        return class_exists('Laravel\Horizon\Horizon');
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.queue.enabled', true);
    }
}
