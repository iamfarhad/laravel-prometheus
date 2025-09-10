<?php

declare(strict_types=1);

/**
 * Example: Enhanced Queue Monitoring with Laravel Horizon
 *
 * This example demonstrates comprehensive queue monitoring setup
 * for production environments using Prometheus metrics.
 */

// 1. Configuration - config/prometheus.php
return [
    'enabled' => true,
    'namespace' => 'myapp',

    'collectors' => [
        'queue' => [
            'enabled' => true,
            'enhanced' => true,  // Enable enhanced monitoring
            'histogram_buckets' => [0.1, 0.5, 1.0, 5.0, 10.0, 30.0, 60.0, 300.0, 600.0],
        ],

        'horizon' => [
            'enabled' => true,   // Only works when Horizon is installed
            'update_interval' => 60,
        ],
    ],
];

// 2. Environment Configuration - .env
/*
PROMETHEUS_COLLECTOR_QUEUE_ENABLED=true
PROMETHEUS_COLLECTOR_QUEUE_ENHANCED=true
PROMETHEUS_COLLECTOR_HORIZON_ENABLED=true
*/

// 3. Scheduler Setup - app/Console/Kernel.php
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Update queue metrics every minute
        $schedule->command('prometheus:update-queue-metrics')
            ->everyMinute()
            ->withoutOverlapping();
    }
}

// 4. Example Job with Custom Metrics
use Iamfarhad\Prometheus\Facades\Prometheus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderJob implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public $queue = 'orders';

    public $timeout = 300;

    public $tries = 3;

    public function __construct(private int $orderId) {}

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            // Your job logic here
            $this->processOrder($this->orderId);

            // Track custom business metrics
            Prometheus::counter('orders_processed_total')->inc([
                'status' => 'success',
                'payment_method' => $this->getPaymentMethod(),
            ]);
        } catch (\Exception $e) {
            // Enhanced queue collector will automatically track this failure
            // But you can add custom business-specific error tracking
            Prometheus::counter('orders_processed_total')->inc([
                'status' => 'failed',
                'error_type' => get_class($e),
            ]);

            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;

            // Track business processing time (separate from queue metrics)
            Prometheus::histogram('order_processing_duration_seconds')->observe(
                $duration,
                ['complexity' => $this->getOrderComplexity()]
            );
        }
    }

    private function processOrder(int $orderId): void
    {
        // Simulate order processing
        sleep(rand(1, 5));
    }

    private function getPaymentMethod(): string
    {
        return 'credit_card'; // Or fetch from order
    }

    private function getOrderComplexity(): string
    {
        return 'standard'; // simple, standard, complex
    }
}

// 5. Horizon Configuration - config/horizon.php
return [
    'environments' => [
        'production' => [
            'supervisor-orders' => [
                'connection' => 'redis',
                'queue' => ['orders', 'high-priority'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 10,
                'tries' => 3,
            ],

            'supervisor-general' => [
                'connection' => 'redis',
                'queue' => ['default', 'low-priority'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 5,
                'tries' => 3,
            ],
        ],
    ],
];

// 6. Example Grafana Dashboard Queries

/*
Queue Job Processing Rate:
rate(myapp_queue_jobs_total[5m])

Queue Job Duration (95th percentile):
histogram_quantile(0.95, rate(myapp_queue_job_duration_seconds_bucket[5m]))

Queue Depth:
myapp_queue_size{status="pending"}

Failed Jobs Rate:
rate(myapp_queue_jobs_total{status="failed"}[5m])

Worker Restarts:
increase(myapp_queue_worker_restarts_total[1h])

Queue Wait Time:
myapp_queue_job_wait_time_seconds

Horizon Supervisor Health:
myapp_horizon_supervisors_active

Workload Balance:
myapp_horizon_workload_balance_ratio
*/

// 7. Alerting Rules (Prometheus AlertManager)

/*
# Queue depth alert
groups:
  - name: queue_alerts
    rules:
      - alert: HighQueueDepth
        expr: myapp_queue_size{status="pending"} > 1000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High queue depth detected"
          description: "Queue {{ $labels.queue }} has {{ $value }} pending jobs"

      - alert: QueueJobFailureRate
        expr: rate(myapp_queue_jobs_total{status="failed"}[5m]) > 0.1
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High job failure rate"
          description: "Queue {{ $labels.queue }} failure rate: {{ $value }} jobs/sec"

      - alert: NoActiveWorkers
        expr: myapp_queue_workers == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "No active queue workers"
          description: "Queue {{ $labels.queue }} has no active workers"
*/

// 8. Custom Queue Monitoring Middleware
class QueueMonitoringMiddleware
{
    public function handle($job, $next)
    {
        $startTime = microtime(true);
        $jobClass = get_class($job);

        // Track job priority/importance
        $priority = $this->getJobPriority($job);

        Prometheus::gauge('queue_job_priority')->set($priority, [
            'job_class' => $jobClass,
            'queue' => $job->queue ?? 'default',
        ]);

        $result = $next($job);

        $duration = microtime(true) - $startTime;

        // Track custom job characteristics
        Prometheus::histogram('queue_job_business_duration_seconds')->observe(
            $duration,
            [
                'job_class' => $jobClass,
                'priority' => $priority,
                'size' => $this->getJobSize($job),
            ]
        );

        return $result;
    }

    private function getJobPriority($job): string
    {
        if (str_contains($job->queue ?? '', 'high')) {
            return 'high';
        }
        if (str_contains($job->queue ?? '', 'low')) {
            return 'low';
        }

        return 'normal';
    }

    private function getJobSize($job): string
    {
        $size = strlen(serialize($job));
        if ($size > 10000) {
            return 'large';
        }
        if ($size > 1000) {
            return 'medium';
        }

        return 'small';
    }
}

// 9. Production Deployment Example
/*
# Docker Compose with monitoring stack
version: '3.8'
services:
  app:
    build: .
    environment:
      - PROMETHEUS_COLLECTOR_QUEUE_ENHANCED=true
      - PROMETHEUS_COLLECTOR_HORIZON_ENABLED=true

  horizon:
    build: .
    command: php artisan horizon
    depends_on:
      - redis

  scheduler:
    build: .
    command: php artisan schedule:work
    depends_on:
      - app

  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
*/
