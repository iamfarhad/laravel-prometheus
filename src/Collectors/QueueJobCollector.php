<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Summary;

final class QueueJobCollector implements CollectorInterface
{
    private ?Counter $queueJobCounter = null;

    private ?Histogram $queueJobDurationHistogram = null;

    private ?Gauge $queueActiveJobsGauge = null;

    private ?Summary $queueJobDurationSummary = null;

    private array $jobStartTimes = [];

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

        $this->queueJobCounter = $this->prometheus->getOrRegisterCounter(
            'queue_jobs_total',
            'Total number of queue jobs processed',
            ['queue', 'connection', 'status', 'job_class']
        );

        $buckets = config('prometheus.collectors.queue.histogram_buckets', [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0]);

        $this->queueJobDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'queue_job_duration_seconds',
            'Queue job processing duration in seconds',
            ['queue', 'job_class'],
            $buckets
        );

        $this->queueActiveJobsGauge = $this->prometheus->getOrRegisterGauge(
            'queue_active_jobs',
            'Current number of active jobs being processed',
            ['queue', 'connection']
        );

        // Summary metric for queue job processing time percentiles
        $quantiles = config('prometheus.collectors.queue.summary_quantiles', [0.5, 0.95, 0.99]);
        $maxAge = config('prometheus.collectors.queue.summary_max_age', 600); // 10 minutes

        $this->queueJobDurationSummary = $this->prometheus->getOrRegisterSummary(
            'queue_job_duration_seconds_summary',
            'Queue job processing duration summary with quantiles',
            ['queue', 'job_class'],
            $maxAge,
            $quantiles
        );
    }

    protected function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Listen to queue events
        Event::listen(JobProcessing::class, [$this, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [$this, 'handleJobProcessed']);
        Event::listen(JobFailed::class, [$this, 'handleJobFailed']);
        Event::listen(JobExceptionOccurred::class, [$this, 'handleJobExceptionOccurred']);
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $this->getJobId($event->job);
        $this->jobStartTimes[$jobId] = microtime(true);

        // Increment active jobs gauge
        if ($this->queueActiveJobsGauge) {
            $this->queueActiveJobsGauge->inc([
                'queue' => $event->job->getQueue() ?: 'default',
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
    }

    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        $this->recordJobCompletion($event->job, $event->connectionName, 'exception');
    }

    protected function recordJobCompletion(mixed $job, string $connectionName, string $status): void
    {
        $jobId = $this->getJobId($job);
        $queue = $job->getQueue() ?: 'default';
        $jobClass = get_class($job);

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
        if (isset($this->jobStartTimes[$jobId])) {
            $duration = microtime(true) - $this->jobStartTimes[$jobId];

            if ($this->queueJobDurationHistogram) {
                $this->queueJobDurationHistogram->observe($duration, [
                    'queue' => $queue,
                    'job_class' => $jobClass,
                ]);
            }

            // Record job duration in summary for percentiles
            if ($this->queueJobDurationSummary) {
                $this->queueJobDurationSummary->observe($duration, [
                    'queue' => $queue,
                    'job_class' => $jobClass,
                ]);
            }

            unset($this->jobStartTimes[$jobId]);
        }

        // Decrement active jobs gauge
        if ($this->queueActiveJobsGauge) {
            $this->queueActiveJobsGauge->dec([
                'queue' => $queue,
                'connection' => $connectionName,
            ]);
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

        // Fallback to a hash of the job object
        return md5(spl_object_hash($job) . microtime());
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.queue.enabled', true);
    }
}
