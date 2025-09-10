<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Unit\Collectors;

use Iamfarhad\Prometheus\Collectors\HorizonCollector;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Tests\TestCase;

final class HorizonCollectorSimpleTest extends TestCase
{
    private HorizonCollector $collector;

    private Prometheus $prometheus;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prometheus.enabled' => true]);
        config(['prometheus.collectors.horizon.enabled' => true]);

        $this->prometheus = $this->app->make(Prometheus::class);
        $this->prometheus->clear();

        $this->collector = new HorizonCollector($this->prometheus);
    }

    public function test_is_enabled_respects_configuration(): void
    {
        // Since Horizon is not available in test environment, collector should return false
        // even when configuration says it's enabled
        $this->assertFalse($this->collector->isEnabled());
    }

    public function test_is_enabled_returns_false_when_prometheus_disabled(): void
    {
        config(['prometheus.enabled' => false]);

        $collector = new HorizonCollector($this->prometheus);

        $this->assertFalse($collector->isEnabled());
    }

    public function test_is_enabled_returns_false_when_horizon_collector_disabled(): void
    {
        config(['prometheus.collectors.horizon.enabled' => false]);

        $collector = new HorizonCollector($this->prometheus);

        $this->assertFalse($collector->isEnabled());
    }

    public function test_is_horizon_available_returns_false_in_test_environment(): void
    {
        $isAvailable = $this->invokePrivateMethod($this->collector, 'isHorizonAvailable');

        $this->assertFalse($isAvailable);
    }

    public function test_extract_supervisor_name_from_different_event_structures(): void
    {
        // Test with supervisor property
        $eventWithSupervisor = new \stdClass;
        $supervisor = new \stdClass;
        $supervisor->name = 'test-supervisor-1';
        $eventWithSupervisor->supervisor = $supervisor;

        $name = $this->invokePrivateMethod($this->collector, 'extractSupervisorName', [$eventWithSupervisor]);
        $this->assertEquals('test-supervisor-1', $name);

        // Test with direct name property
        $eventWithName = new \stdClass;
        $eventWithName->name = 'direct-supervisor-name';

        $name = $this->invokePrivateMethod($this->collector, 'extractSupervisorName', [$eventWithName]);
        $this->assertEquals('direct-supervisor-name', $name);

        // Test with unknown structure
        $eventUnknown = new \stdClass;

        $name = $this->invokePrivateMethod($this->collector, 'extractSupervisorName', [$eventUnknown]);
        $this->assertEquals('unknown', $name);
    }

    public function test_extract_restart_reason(): void
    {
        // Test with reason property
        $eventWithReason = new \stdClass;
        $eventWithReason->reason = 'memory_limit_exceeded';

        $reason = $this->invokePrivateMethod($this->collector, 'extractRestartReason', [$eventWithReason]);
        $this->assertEquals('memory_limit_exceeded', $reason);

        // Test without reason property
        $eventWithoutReason = new \stdClass;

        $reason = $this->invokePrivateMethod($this->collector, 'extractRestartReason', [$eventWithoutReason]);
        $this->assertEquals('unknown', $reason);
    }

    public function test_extract_termination_reason(): void
    {
        // Test with reason property
        $eventWithReason = new \stdClass;
        $eventWithReason->reason = 'process_timeout';

        $reason = $this->invokePrivateMethod($this->collector, 'extractTerminationReason', [$eventWithReason]);
        $this->assertEquals('process_timeout', $reason);

        // Test without reason property
        $eventWithoutReason = new \stdClass;

        $reason = $this->invokePrivateMethod($this->collector, 'extractTerminationReason', [$eventWithoutReason]);
        $this->assertEquals('unknown', $reason);
    }

    public function test_extract_queue_name(): void
    {
        // Test with queue property
        $eventWithQueue = new \stdClass;
        $eventWithQueue->queue = 'high-priority-queue';

        $queue = $this->invokePrivateMethod($this->collector, 'extractQueueName', [$eventWithQueue]);
        $this->assertEquals('high-priority-queue', $queue);

        // Test without queue property
        $eventWithoutQueue = new \stdClass;

        $queue = $this->invokePrivateMethod($this->collector, 'extractQueueName', [$eventWithoutQueue]);
        $this->assertEquals('unknown', $queue);
    }

    public function test_extract_wait_time(): void
    {
        // Test with waitTime property
        $eventWithWaitTime = new \stdClass;
        $eventWithWaitTime->waitTime = 12.5;

        $waitTime = $this->invokePrivateMethod($this->collector, 'extractWaitTime', [$eventWithWaitTime]);
        $this->assertEquals(12.5, $waitTime);

        // Test without waitTime property
        $eventWithoutWaitTime = new \stdClass;

        $waitTime = $this->invokePrivateMethod($this->collector, 'extractWaitTime', [$eventWithoutWaitTime]);
        $this->assertEquals(0.0, $waitTime);
    }

    public function test_extract_workload_data(): void
    {
        // Test with supervisor workload
        $eventWithSupervisorWorkload = new \stdClass;
        $supervisor = new \stdClass;
        $supervisor->workload = ['default' => 15, 'emails' => 8];
        $eventWithSupervisorWorkload->supervisor = $supervisor;

        $workload = $this->invokePrivateMethod($this->collector, 'extractWorkloadData', [$eventWithSupervisorWorkload]);
        $this->assertEquals(['default' => 15, 'emails' => 8], $workload);

        // Test with direct workload
        $eventWithDirectWorkload = new \stdClass;
        $eventWithDirectWorkload->workload = ['processing' => 5];

        $workload = $this->invokePrivateMethod($this->collector, 'extractWorkloadData', [$eventWithDirectWorkload]);
        $this->assertEquals(['processing' => 5], $workload);

        // Test without workload
        $eventWithoutWorkload = new \stdClass;

        $workload = $this->invokePrivateMethod($this->collector, 'extractWorkloadData', [$eventWithoutWorkload]);
        $this->assertNull($workload);
    }

    public function test_calculate_workload_balance(): void
    {
        // Test with empty workload
        $balance = $this->invokePrivateMethod($this->collector, 'calculateWorkloadBalance', [[]]);
        $this->assertEquals(1.0, $balance);

        // Test with perfectly balanced workload
        $balance = $this->invokePrivateMethod($this->collector, 'calculateWorkloadBalance', [['queue1' => 10, 'queue2' => 10]]);
        $this->assertEquals(1.0, $balance);

        // Test with unbalanced workload
        $balance = $this->invokePrivateMethod($this->collector, 'calculateWorkloadBalance', [['queue1' => 20, 'queue2' => 5]]);
        $this->assertEquals(0.25, $balance);

        // Test with single queue
        $balance = $this->invokePrivateMethod($this->collector, 'calculateWorkloadBalance', [['queue1' => 15]]);
        $this->assertEquals(1.0, $balance);

        // Test with zero values
        $balance = $this->invokePrivateMethod($this->collector, 'calculateWorkloadBalance', [['queue1' => 0, 'queue2' => 0]]);
        $this->assertEquals(1.0, $balance);
    }

    public function test_collector_handles_horizon_unavailability_gracefully(): void
    {
        // Since Horizon is not available in test environment,
        // the collector should handle this without errors

        // Test update runtime metrics
        $this->collector->updateRuntimeMetrics();

        // Test event handlers (these should not throw exceptions)
        $mockEvent = new \stdClass;

        $this->collector->handleMasterSupervisorLooped($mockEvent);
        $this->collector->handleSupervisorLooped($mockEvent);

        // If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    public function test_collector_constructor_completes_without_errors(): void
    {
        // Clear metrics to avoid conflicts
        $this->prometheus->clear();

        // Test that we can create a new collector without errors
        $newCollector = new HorizonCollector($this->prometheus);

        $this->assertInstanceOf(HorizonCollector::class, $newCollector);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    protected function tearDown(): void
    {
        $this->prometheus->clear();
        parent::tearDown();
    }
}
