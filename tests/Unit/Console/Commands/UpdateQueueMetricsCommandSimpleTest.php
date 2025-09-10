<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Unit\Console\Commands;

use Iamfarhad\Prometheus\Console\Commands\UpdateQueueMetricsCommand;
use Iamfarhad\Prometheus\Prometheus;
use Iamfarhad\Prometheus\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class UpdateQueueMetricsCommandSimpleTest extends TestCase
{
    private Prometheus $prometheus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prometheus = $this->app->make(Prometheus::class);
        $this->prometheus->clear();
    }

    public function test_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('prometheus:update-queue-metrics', $commands);
        $this->assertInstanceOf(UpdateQueueMetricsCommand::class, $commands['prometheus:update-queue-metrics']);
    }

    public function test_command_has_correct_signature_and_description(): void
    {
        $command = new UpdateQueueMetricsCommand($this->prometheus);

        $this->assertEquals('prometheus:update-queue-metrics', $command->getName());
        $this->assertEquals('Update Prometheus queue metrics (queue sizes, failed jobs, etc.)', $command->getDescription());
    }

    public function test_command_exits_successfully_when_prometheus_disabled(): void
    {
        config(['prometheus.enabled' => false]);

        $exitCode = Artisan::call('prometheus:update-queue-metrics');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Queue collector is disabled', Artisan::output());
    }

    public function test_command_exits_successfully_when_queue_collector_disabled(): void
    {
        config(['prometheus.enabled' => true]);
        config(['prometheus.collectors.queue.enabled' => false]);

        $exitCode = Artisan::call('prometheus:update-queue-metrics');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Queue collector is disabled', Artisan::output());
    }

    public function test_command_fails_gracefully_when_metrics_not_registered(): void
    {
        config(['prometheus.enabled' => true]);
        config(['prometheus.collectors.queue.enabled' => true]);

        // The command should fail when required metrics are not registered
        // (enhanced queue collector not enabled)
        $exitCode = Artisan::call('prometheus:update-queue-metrics');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Failed to update queue metrics', Artisan::output());
    }

    public function test_get_queues_for_connection_helper_method(): void
    {
        $command = new UpdateQueueMetricsCommand($this->prometheus);

        // Test with string queue config
        config(['queue.connections.test_string.queue' => 'single-queue']);
        $queues = $this->invokePrivateMethod($command, 'getQueuesForConnection', ['test_string']);
        $this->assertEquals(['single-queue'], $queues);

        // Test with array queue config
        config(['queue.connections.test_array.queue' => ['queue1', 'queue2', 'queue3']]);
        $queues = $this->invokePrivateMethod($command, 'getQueuesForConnection', ['test_array']);
        $this->assertEquals(['queue1', 'queue2', 'queue3'], $queues);

        // Test with no queue config (should return defaults)
        config(['queue.connections.test_empty' => []]);
        $queues = $this->invokePrivateMethod($command, 'getQueuesForConnection', ['test_empty']);
        $this->assertEquals(['default', 'high', 'low'], $queues);

        // Test with null queue config
        config(['queue.connections.test_null.queue' => null]);
        $queues = $this->invokePrivateMethod($command, 'getQueuesForConnection', ['test_null']);
        $this->assertEquals(['default', 'high', 'low'], $queues);
    }

    public function test_is_horizon_available_returns_false_in_test_environment(): void
    {
        $command = new UpdateQueueMetricsCommand($this->prometheus);

        $isAvailable = $this->invokePrivateMethod($command, 'isHorizonAvailable');

        // Should return false in test environment (Horizon not installed)
        $this->assertFalse($isAvailable);
    }

    public function test_command_constructor_accepts_prometheus_instance(): void
    {
        $command = new UpdateQueueMetricsCommand($this->prometheus);

        $this->assertInstanceOf(UpdateQueueMetricsCommand::class, $command);
    }

    public function test_command_can_be_instantiated_multiple_times(): void
    {
        $command1 = new UpdateQueueMetricsCommand($this->prometheus);
        $command2 = new UpdateQueueMetricsCommand($this->prometheus);

        $this->assertInstanceOf(UpdateQueueMetricsCommand::class, $command1);
        $this->assertInstanceOf(UpdateQueueMetricsCommand::class, $command2);
        $this->assertNotSame($command1, $command2);
    }

    public function test_command_respects_configuration_changes(): void
    {
        // Test that the command behavior changes with configuration

        // First, disable everything
        config(['prometheus.enabled' => false]);
        $exitCode1 = Artisan::call('prometheus:update-queue-metrics');
        $this->assertEquals(0, $exitCode1);

        // Enable prometheus but disable queue collector
        config(['prometheus.enabled' => true, 'prometheus.collectors.queue.enabled' => false]);
        $exitCode2 = Artisan::call('prometheus:update-queue-metrics');
        $this->assertEquals(0, $exitCode2);

        // Enable both (should fail because enhanced collector not enabled)
        config(['prometheus.enabled' => true, 'prometheus.collectors.queue.enabled' => true]);
        $exitCode3 = Artisan::call('prometheus:update-queue-metrics');
        $this->assertEquals(1, $exitCode3);
    }

    public function test_command_output_contains_expected_messages(): void
    {
        config(['prometheus.enabled' => true]);
        config(['prometheus.collectors.queue.enabled' => true]);

        $exitCode = Artisan::call('prometheus:update-queue-metrics');
        $output = Artisan::output();

        // Should contain updating message
        $this->assertStringContainsString('Updating queue metrics', $output);

        // Should contain failure message (since enhanced collector not enabled)
        $this->assertStringContainsString('Failed to update queue metrics', $output);

        $this->assertEquals(1, $exitCode);
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
