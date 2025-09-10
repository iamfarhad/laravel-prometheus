<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Histogram;
use Throwable;

final class EventCollector implements CollectorInterface
{
    private ?Counter $eventsFiredCounter = null;

    private ?Counter $failedEventsCounter = null;

    private ?Histogram $eventProcessingDurationHistogram = null;

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

        $this->eventsFiredCounter = $this->prometheus->getOrRegisterCounter(
            'events_fired_total',
            'Total number of Laravel events fired',
            ['event_class', 'status']
        );

        $this->failedEventsCounter = $this->prometheus->getOrRegisterCounter(
            'failed_events_total',
            'Total number of failed event processing',
            ['event_class', 'listener_class', 'error_type']
        );

        $this->eventProcessingDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'event_processing_duration_seconds',
            'Time spent processing Laravel events',
            ['event_class', 'listener_class'],
            [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
        );
    }

    public function isEnabled(): bool
    {
        return config('prometheus.collectors.events.enabled', false);
    }

    private function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Listen to all events using a wildcard listener
        Event::listen('*', function ($eventName, $payload) {
            $this->recordEventFired($eventName, $payload);
        });
    }

    public function recordEventFired(string $eventName, array $payload = []): void
    {
        if (! $this->isEnabled() || ! $this->eventsFiredCounter) {
            return;
        }

        // Extract event class name from full event name
        $eventClass = $this->extractEventClass($eventName);

        try {
            $this->eventsFiredCounter->inc([
                'event_class' => $eventClass,
                'status' => 'fired',
            ]);
        } catch (Throwable $e) {
            // Don't let metrics collection break the application
            report($e);
        }
    }

    public function recordEventProcessingStart(string $eventClass, string $listenerClass): array
    {
        return [
            'event_class' => $eventClass,
            'listener_class' => $listenerClass,
            'start_time' => microtime(true),
        ];
    }

    public function recordEventProcessingEnd(array $context): void
    {
        if (! $this->isEnabled() || ! $this->eventProcessingDurationHistogram) {
            return;
        }

        try {
            $duration = microtime(true) - $context['start_time'];

            $this->eventProcessingDurationHistogram->observe($duration, [
                'event_class' => $context['event_class'],
                'listener_class' => $context['listener_class'],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function recordEventProcessingFailure(string $eventClass, string $listenerClass, Throwable $exception): void
    {
        if (! $this->isEnabled() || ! $this->failedEventsCounter) {
            return;
        }

        try {
            $this->failedEventsCounter->inc([
                'event_class' => $eventClass,
                'listener_class' => $listenerClass,
                'error_type' => get_class($exception),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function extractEventClass(string $eventName): string
    {
        // Handle class-based events (check if it looks like a class name)
        if (str_contains($eventName, '\\')) {
            return class_basename($eventName);
        }

        // Handle string-based events
        $parts = explode('.', $eventName);

        return end($parts) ?: $eventName;
    }
}
