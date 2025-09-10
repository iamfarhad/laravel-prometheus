<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Throwable;

final class HorizonCollector implements CollectorInterface
{
    // Supervisor metrics
    private ?Gauge $supervisorsGauge = null;

    private ?Counter $supervisorLoopsCounter = null;

    private ?Counter $masterLoopsCounter = null;

    // Worker metrics
    private ?Gauge $workersGauge = null;

    private ?Counter $workerRestartsCounter = null;

    private ?Gauge $workerMemoryGauge = null;

    // Workload metrics
    private ?Gauge $workloadGauge = null;

    private ?Histogram $workloadBalanceHistogram = null;

    // Process metrics
    private ?Counter $processesStartedCounter = null;

    private ?Counter $processesTerminatedCounter = null;

    private ?Gauge $processMemoryGauge = null;

    public function __construct(private Prometheus $prometheus)
    {
        if ($this->isHorizonAvailable()) {
            $this->registerMetrics();
            $this->registerEventListeners();
        }
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Supervisor metrics
        $this->supervisorsGauge = $this->prometheus->getOrRegisterGauge(
            'horizon_supervisors_active',
            'Number of active Horizon supervisors',
            ['environment', 'supervisor_name']
        );

        $this->supervisorLoopsCounter = $this->prometheus->getOrRegisterCounter(
            'horizon_supervisor_loops_total',
            'Total number of supervisor loops executed',
            ['environment', 'supervisor_name']
        );

        $this->masterLoopsCounter = $this->prometheus->getOrRegisterCounter(
            'horizon_master_loops_total',
            'Total number of master supervisor loops executed',
            ['environment']
        );

        // Worker metrics
        $this->workersGauge = $this->prometheus->getOrRegisterGauge(
            'horizon_workers_active',
            'Number of active Horizon workers',
            ['environment', 'supervisor', 'queue']
        );

        $this->workerRestartsCounter = $this->prometheus->getOrRegisterCounter(
            'horizon_worker_restarts_total',
            'Total number of worker restarts',
            ['environment', 'supervisor', 'reason']
        );

        $this->workerMemoryGauge = $this->prometheus->getOrRegisterGauge(
            'horizon_worker_memory_bytes',
            'Worker memory usage in bytes',
            ['environment', 'supervisor', 'worker_id']
        );

        // Workload metrics
        $this->workloadGauge = $this->prometheus->getOrRegisterGauge(
            'horizon_workload',
            'Current workload distribution per queue',
            ['environment', 'queue', 'supervisor']
        );

        $this->workloadBalanceHistogram = $this->prometheus->getOrRegisterHistogram(
            'horizon_workload_balance_ratio',
            'Workload balance ratio between queues',
            ['environment', 'supervisor'],
            [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0]
        );

        // Process metrics
        $this->processesStartedCounter = $this->prometheus->getOrRegisterCounter(
            'horizon_processes_started_total',
            'Total number of processes started',
            ['environment', 'supervisor', 'queue']
        );

        $this->processesTerminatedCounter = $this->prometheus->getOrRegisterCounter(
            'horizon_processes_terminated_total',
            'Total number of processes terminated',
            ['environment', 'supervisor', 'reason']
        );

        $this->processMemoryGauge = $this->prometheus->getOrRegisterGauge(
            'horizon_process_memory_bytes',
            'Process memory usage in bytes',
            ['environment', 'supervisor', 'process_id']
        );
    }

    protected function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Map of Horizon event classes to handler methods
        $eventHandlers = [
            'Laravel\Horizon\Events\MasterSupervisorLooped' => 'handleMasterSupervisorLooped',
            'Laravel\Horizon\Events\SupervisorLooped' => 'handleSupervisorLooped',
            'Laravel\Horizon\Events\WorkerProcessRestarting' => 'handleWorkerProcessRestarting',
            'Laravel\Horizon\Events\WorkerProcessTerminated' => 'handleWorkerProcessTerminated',
            'Laravel\Horizon\Events\LongWaitDetected' => 'handleLongWaitDetected',
            'Laravel\Horizon\Events\JobDeleted' => 'handleJobDeleted',
            'Laravel\Horizon\Events\JobPushed' => 'handleJobPushed',
            'Laravel\Horizon\Events\JobReserved' => 'handleJobReserved',
            'Laravel\Horizon\Events\JobReleased' => 'handleJobReleased',
        ];

        foreach ($eventHandlers as $eventClass => $handlerMethod) {
            if (class_exists($eventClass) && method_exists($this, $handlerMethod)) {
                Event::listen($eventClass, [$this, $handlerMethod]);
            }
        }
    }

    public function handleMasterSupervisorLooped($event): void
    {
        if ($this->masterLoopsCounter) {
            $this->masterLoopsCounter->inc([
                'environment' => app()->environment(),
            ]);
        }
    }

    public function handleSupervisorLooped($event): void
    {
        if ($this->supervisorLoopsCounter) {
            $supervisorName = $this->extractSupervisorName($event);

            $this->supervisorLoopsCounter->inc([
                'environment' => app()->environment(),
                'supervisor_name' => $supervisorName,
            ]);
        }

        // Update supervisor active status
        if ($this->supervisorsGauge) {
            $supervisorName = $this->extractSupervisorName($event);

            $this->supervisorsGauge->set(1, [
                'environment' => app()->environment(),
                'supervisor_name' => $supervisorName,
            ]);
        }

        // Update workload metrics if data is available
        $this->updateWorkloadMetrics($event);
    }

    public function handleWorkerProcessRestarting($event): void
    {
        if ($this->workerRestartsCounter) {
            $this->workerRestartsCounter->inc([
                'environment' => app()->environment(),
                'supervisor' => $this->extractSupervisorName($event),
                'reason' => $this->extractRestartReason($event),
            ]);
        }
    }

    public function handleWorkerProcessTerminated($event): void
    {
        if ($this->processesTerminatedCounter) {
            $this->processesTerminatedCounter->inc([
                'environment' => app()->environment(),
                'supervisor' => $this->extractSupervisorName($event),
                'reason' => $this->extractTerminationReason($event),
            ]);
        }
    }

    public function handleLongWaitDetected($event): void
    {
        // This could be used to track queue congestion
        // For now, we'll just log it
        logger()->info('Horizon long wait detected', [
            'queue' => $this->extractQueueName($event),
            'wait_time' => $this->extractWaitTime($event),
        ]);
    }

    public function handleJobDeleted($event): void
    {
        // Track job deletions which might indicate failures or timeouts
    }

    public function handleJobPushed($event): void
    {
        // Track job pushes for workload analysis
    }

    public function handleJobReserved($event): void
    {
        // Track job reservations
    }

    public function handleJobReleased($event): void
    {
        // Track job releases (retries)
    }

    protected function updateWorkloadMetrics($event): void
    {
        try {
            // Extract workload data from supervisor loop event
            $workloadData = $this->extractWorkloadData($event);

            if (! $workloadData) {
                return;
            }

            $supervisorName = $this->extractSupervisorName($event);
            $environment = app()->environment();

            foreach ($workloadData as $queue => $workload) {
                if ($this->workloadGauge) {
                    $this->workloadGauge->set($workload, [
                        'environment' => $environment,
                        'queue' => $queue,
                        'supervisor' => $supervisorName,
                    ]);
                }
            }

            // Calculate and record workload balance
            if ($this->workloadBalanceHistogram && count($workloadData) > 1) {
                $balance = $this->calculateWorkloadBalance($workloadData);
                $this->workloadBalanceHistogram->observe($balance, [
                    'environment' => $environment,
                    'supervisor' => $supervisorName,
                ]);
            }
        } catch (Throwable $e) {
            logger()->warning('Failed to update workload metrics: '.$e->getMessage());
        }
    }

    protected function extractSupervisorName($event): string
    {
        // Different Horizon versions might have different event structures
        if (isset($event->supervisor)) {
            return $event->supervisor->name ?? 'unknown';
        }

        if (isset($event->name)) {
            return $event->name;
        }

        return 'unknown';
    }

    protected function extractRestartReason($event): string
    {
        return $event->reason ?? 'unknown';
    }

    protected function extractTerminationReason($event): string
    {
        return $event->reason ?? 'unknown';
    }

    protected function extractQueueName($event): string
    {
        return $event->queue ?? 'unknown';
    }

    protected function extractWaitTime($event): float
    {
        return $event->waitTime ?? 0.0;
    }

    protected function extractWorkloadData($event): ?array
    {
        // This depends on the actual Horizon event structure
        // Different versions might expose workload data differently

        if (isset($event->supervisor->workload)) {
            return $event->supervisor->workload;
        }

        if (isset($event->workload)) {
            return $event->workload;
        }

        return null;
    }

    protected function calculateWorkloadBalance(array $workloadData): float
    {
        if (empty($workloadData)) {
            return 1.0;
        }

        $values = array_values($workloadData);
        $max = max($values);
        $min = min($values);

        // Return balance ratio (1.0 = perfectly balanced, 0.0 = completely unbalanced)
        return $max > 0 ? $min / $max : 1.0;
    }

    public function updateRuntimeMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $this->updateSupervisorMetrics();
            $this->updateWorkerMetrics();
            $this->updateProcessMetrics();
        } catch (Throwable $e) {
            logger()->warning('Failed to update Horizon runtime metrics: '.$e->getMessage());
        }
    }

    protected function updateSupervisorMetrics(): void
    {
        // This would require access to Horizon's internal supervisor registry
        // Implementation depends on Horizon version and available APIs
    }

    protected function updateWorkerMetrics(): void
    {
        // Update worker counts and memory usage
        // This would require access to Horizon's worker registry
    }

    protected function updateProcessMetrics(): void
    {
        // Update process memory and CPU usage
        // This would require system-level process monitoring
    }

    protected function isHorizonAvailable(): bool
    {
        return class_exists('Laravel\Horizon\Horizon');
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.horizon.enabled', false) &&
            $this->isHorizonAvailable();
    }
}
