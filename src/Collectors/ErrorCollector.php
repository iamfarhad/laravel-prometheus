<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Support\Facades\Log;
use Prometheus\Counter;
use Prometheus\Gauge;
use Throwable;

final class ErrorCollector implements CollectorInterface
{
    private ?Counter $applicationErrorsCounter = null;

    private ?Counter $httpErrorsCounter = null;

    private ?Counter $criticalErrorsCounter = null;

    private ?Gauge $errorRateGauge = null;

    private array $errorCounts = [];

    private array $totalRequests = [];

    public function __construct(private Prometheus $prometheus)
    {
        $this->registerMetrics();
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->applicationErrorsCounter = $this->prometheus->getOrRegisterCounter(
            'application_errors_total',
            'Total number of application errors by exception type',
            ['exception_class', 'severity', 'component']
        );

        $this->httpErrorsCounter = $this->prometheus->getOrRegisterCounter(
            'application_response_errors_total',
            'Total number of HTTP error responses',
            ['http_status', 'method', 'route']
        );

        $this->criticalErrorsCounter = $this->prometheus->getOrRegisterCounter(
            'application_critical_errors_total',
            'Total number of critical application errors',
            ['exception_class', 'component']
        );

        $this->errorRateGauge = $this->prometheus->getOrRegisterGauge(
            'application_error_rate',
            'Current application error rate percentage',
            ['component', 'time_window']
        );
    }

    public function isEnabled(): bool
    {
        return config('prometheus.collectors.errors.enabled', false);
    }

    public function recordException(Throwable $exception, string $component = 'unknown', array $context = []): void
    {
        if (! $this->isEnabled() || ! $this->applicationErrorsCounter) {
            return;
        }

        try {
            $severity = $this->determineSeverity($exception);
            $exceptionClass = get_class($exception);

            $this->applicationErrorsCounter->inc([
                'exception_class' => class_basename($exceptionClass),
                'severity' => $severity,
                'component' => $component,
            ]);

            // Record critical errors separately
            if ($severity === 'critical' && $this->criticalErrorsCounter) {
                $this->criticalErrorsCounter->inc([
                    'exception_class' => class_basename($exceptionClass),
                    'component' => $component,
                ]);
            }

            // Update error rate tracking
            $this->updateErrorRate($component);
        } catch (Throwable $e) {
            // Don't let metrics collection break the application
            Log::error('Failed to record exception metrics', [
                'original_exception' => $exception->getMessage(),
                'metrics_exception' => $e->getMessage(),
            ]);
        }
    }

    public function recordHttpError(int $statusCode, string $method, string $route = 'unknown'): void
    {
        if (! $this->isEnabled() || ! $this->httpErrorsCounter) {
            return;
        }

        // Only record actual errors (4xx, 5xx)
        if ($statusCode < 400) {
            return;
        }

        try {
            $this->httpErrorsCounter->inc([
                'http_status' => (string) $statusCode,
                'method' => strtoupper($method),
                'route' => $this->sanitizeRouteName($route),
            ]);

            $component = $statusCode >= 500 ? 'server' : 'client';
            $this->updateErrorRate($component);
        } catch (Throwable $e) {
            Log::error('Failed to record HTTP error metrics', [
                'status_code' => $statusCode,
                'method' => $method,
                'route' => $route,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordRequest(string $component = 'http'): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $minute = floor(time() / 60);
        $this->totalRequests[$component][$minute] = ($this->totalRequests[$component][$minute] ?? 0) + 1;

        // Clean old data (keep last 5 minutes)
        $cutoff = $minute - 5;
        foreach ($this->totalRequests as $comp => $minutes) {
            $this->totalRequests[$comp] = array_filter($minutes, fn ($min) => $min > $cutoff, ARRAY_FILTER_USE_KEY);
        }
    }

    private function updateErrorRate(string $component): void
    {
        if (! $this->errorRateGauge) {
            return;
        }

        $minute = floor(time() / 60);
        $this->errorCounts[$component][$minute] = ($this->errorCounts[$component][$minute] ?? 0) + 1;

        // Calculate error rate for last 5 minutes
        $recentErrors = array_sum($this->errorCounts[$component] ?? []);
        $recentTotal = array_sum($this->totalRequests[$component] ?? []);

        if ($recentTotal > 0) {
            $errorRate = ($recentErrors / $recentTotal) * 100;

            try {
                $this->errorRateGauge->set($errorRate, [
                    'component' => $component,
                    'time_window' => '5m',
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to update error rate gauge', ['error' => $e->getMessage()]);
            }
        }

        // Clean old error data
        $cutoff = $minute - 5;
        foreach ($this->errorCounts as $comp => $minutes) {
            $this->errorCounts[$comp] = array_filter($minutes, fn ($min) => $min > $cutoff, ARRAY_FILTER_USE_KEY);
        }
    }

    private function determineSeverity(Throwable $exception): string
    {
        $exceptionClass = get_class($exception);

        // Critical errors that require immediate attention
        $criticalExceptions = [
            'Error',
            'ParseError',
            'TypeError',
            'ArgumentCountError',
            'OutOfMemoryError',
            'PDOException',
            'ErrorException',
        ];

        foreach ($criticalExceptions as $critical) {
            if (str_contains($exceptionClass, $critical)) {
                return 'critical';
            }
        }

        // Warning level exceptions
        $warningExceptions = [
            'NotFoundHttpException',
            'ValidationException',
            'AuthenticationException',
            'AuthorizationException',
        ];

        foreach ($warningExceptions as $warning) {
            if (str_contains($exceptionClass, $warning)) {
                return 'warning';
            }
        }

        // Default to error level
        return 'error';
    }

    private function sanitizeRouteName(string $route): string
    {
        // Remove parameters and sensitive information
        $route = preg_replace('/\{[^}]+\}/', '{id}', $route) ?? $route;
        $route = preg_replace('/\/\d+/', '/{id}', $route) ?? $route;

        return substr($route, 0, 100); // Limit length
    }
}
