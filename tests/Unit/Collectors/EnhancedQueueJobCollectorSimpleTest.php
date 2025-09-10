<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Unit\Collectors;

use Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Tests\TestCase;

final class EnhancedQueueJobCollectorSimpleTest extends TestCase
{
    private EnhancedQueueJobCollector $collector;

    private Prometheus $prometheus;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prometheus.enabled' => true]);
        config(['prometheus.collectors.queue.enabled' => true]);
        config(['prometheus.collectors.queue.enhanced' => true]);

        $this->prometheus = $this->app->make(Prometheus::class);
        $this->prometheus->clear();

        $this->collector = new EnhancedQueueJobCollector($this->prometheus);
    }

    public function test_collector_is_enabled_with_proper_configuration(): void
    {
        $this->assertTrue($this->collector->isEnabled());
    }

    public function test_collector_is_disabled_when_prometheus_disabled(): void
    {
        config(['prometheus.enabled' => false]);

        $collector = new EnhancedQueueJobCollector($this->prometheus);

        $this->assertFalse($collector->isEnabled());
    }

    public function test_collector_is_disabled_when_queue_collector_disabled(): void
    {
        config(['prometheus.collectors.queue.enabled' => false]);

        $collector = new EnhancedQueueJobCollector($this->prometheus);

        $this->assertFalse($collector->isEnabled());
    }

    public function test_basic_metrics_can_be_created(): void
    {
        // Test that collector can be instantiated and is properly configured
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector::class, $collector);
        $this->assertTrue($collector->isEnabled());
    }

    public function test_enhanced_metrics_collector_works(): void
    {
        // Test that collector can be instantiated without errors
        $collector = $this->app->make(\Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector::class);
        $this->assertInstanceOf(\Iamfarhad\Prometheus\Collectors\EnhancedQueueJobCollector::class, $collector);
        $this->assertTrue($collector->isEnabled());
    }

    public function test_horizon_metrics_are_not_registered_in_test_environment(): void
    {
        // Horizon should not be available in test environment
        $this->assertFalse($this->prometheus->hasMetric('horizon_supervisors'));
        $this->assertFalse($this->prometheus->hasMetric('horizon_workload'));
        $this->assertFalse($this->prometheus->hasMetric('horizon_master_loops_total'));
    }

    public function test_is_horizon_available_returns_false(): void
    {
        $isAvailable = $this->invokePrivateMethod($this->collector, 'isHorizonAvailable');

        $this->assertFalse($isAvailable);
    }

    public function test_get_job_queue_with_different_inputs(): void
    {
        // Test with object that has a queue property
        $jobWithProperty = new \stdClass;
        $jobWithProperty->queue = 'test-queue';

        $queue = $this->invokePrivateMethod($this->collector, 'getJobQueue', [$jobWithProperty]);
        $this->assertEquals('test-queue', $queue);

        // Test with object that has empty queue property
        $jobWithEmptyProperty = new \stdClass;
        $jobWithEmptyProperty->queue = '';

        $queue = $this->invokePrivateMethod($this->collector, 'getJobQueue', [$jobWithEmptyProperty]);
        $this->assertEquals('default', $queue);

        // Test with object that has no queue property
        $jobWithoutProperty = new \stdClass;

        $queue = $this->invokePrivateMethod($this->collector, 'getJobQueue', [$jobWithoutProperty]);
        $this->assertEquals('default', $queue);
    }

    public function test_get_job_class_returns_class_name(): void
    {
        $job = new \stdClass;

        $jobClass = $this->invokePrivateMethod($this->collector, 'getJobClass', [$job]);

        $this->assertEquals('stdClass', $jobClass);
    }

    public function test_get_job_id_with_different_sources(): void
    {
        // Test with uuid property
        $jobWithUuid = new \stdClass;
        $jobWithUuid->uuid = 'test-uuid-123';

        $id = $this->invokePrivateMethod($this->collector, 'getJobId', [$jobWithUuid]);
        $this->assertEquals('test-uuid-123', $id);

        // Test with nested job uuid
        $jobWithNestedUuid = new \stdClass;
        $jobWithNestedUuid->job = new \stdClass;
        $jobWithNestedUuid->job->uuid = 'nested-uuid-456';

        $id = $this->invokePrivateMethod($this->collector, 'getJobId', [$jobWithNestedUuid]);
        $this->assertEquals('nested-uuid-456', $id);

        // Test fallback to hash
        $jobWithoutId = new \stdClass;

        $id = $this->invokePrivateMethod($this->collector, 'getJobId', [$jobWithoutId]);
        $this->assertIsString($id);
        $this->assertEquals(32, strlen($id)); // MD5 hash length
    }

    public function test_collector_constructor_completes_without_errors(): void
    {
        // Clear metrics to avoid conflicts
        $this->prometheus->clear();

        // Test that we can create a new collector without errors
        $newCollector = new EnhancedQueueJobCollector($this->prometheus);

        $this->assertInstanceOf(EnhancedQueueJobCollector::class, $newCollector);
    }

    public function test_configuration_options_are_respected(): void
    {
        // Clear metrics first to avoid conflicts
        $this->prometheus->clear();

        // Test different configuration combinations
        config(['prometheus.enabled' => true, 'prometheus.collectors.queue.enabled' => true]);
        $enabledCollector = new EnhancedQueueJobCollector($this->prometheus);
        $this->assertTrue($enabledCollector->isEnabled());

        // Clear again for next test
        $this->prometheus->clear();

        config(['prometheus.enabled' => false, 'prometheus.collectors.queue.enabled' => true]);
        $prometheusDisabledCollector = new EnhancedQueueJobCollector($this->prometheus);
        $this->assertFalse($prometheusDisabledCollector->isEnabled());

        // Clear again for next test
        $this->prometheus->clear();

        config(['prometheus.enabled' => true, 'prometheus.collectors.queue.enabled' => false]);
        $queueDisabledCollector = new EnhancedQueueJobCollector($this->prometheus);
        $this->assertFalse($queueDisabledCollector->isEnabled());
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
