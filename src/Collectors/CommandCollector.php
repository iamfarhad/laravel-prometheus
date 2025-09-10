<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Prometheus\Counter;
use Prometheus\Histogram;

final class CommandCollector implements CollectorInterface
{
    private ?Counter $commandCounter = null;

    private ?Histogram $commandDurationHistogram = null;

    private array $commandStartTimes = [];

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

        $this->commandCounter = $this->prometheus->getOrRegisterCounter(
            'artisan_commands_total',
            'Total number of Artisan commands executed',
            ['command', 'status']
        );

        $buckets = config('prometheus.collectors.command.histogram_buckets', [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0]);

        $this->commandDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'artisan_command_duration_seconds',
            'Artisan command execution time in seconds',
            ['command', 'status'],
            $buckets
        );
    }

    protected function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $this->recordCommandStart($event);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            $this->recordCommandFinish($event);
        });
    }

    protected function recordCommandStart(CommandStarting $event): void
    {
        $commandName = $this->extractCommandName($event->command);
        $this->commandStartTimes[$commandName] = microtime(true);
    }

    protected function recordCommandFinish(CommandFinished $event): void
    {
        $commandName = $this->extractCommandName($event->command);
        $status = $this->getCommandStatus($event->exitCode);
        $endTime = microtime(true);

        // Calculate duration if we have a start time
        $duration = null;
        if (isset($this->commandStartTimes[$commandName])) {
            $duration = $endTime - $this->commandStartTimes[$commandName];
            unset($this->commandStartTimes[$commandName]);
        }

        $labels = [
            'command' => $commandName,
            'status' => $status,
        ];

        // Record command count
        if ($this->commandCounter) {
            $this->commandCounter->inc($labels);
        }

        // Record command duration if available
        if ($duration !== null && $this->commandDurationHistogram) {
            $this->commandDurationHistogram->observe($duration, $labels);
        }
    }

    protected function extractCommandName(string $command): string
    {
        // Clean up the command name for metrics
        $command = trim($command);

        // Remove common prefixes and clean up
        $command = preg_replace('/^artisan\s+/', '', $command);

        // Extract the command name (before any options/arguments that start with -)
        // This preserves commands like "route:cache", "config:clear", etc.
        if (preg_match('/^([^\s-]+(?::[^\s-]+)*)/', $command, $matches)) {
            $commandName = $matches[1];
        } else {
            // Fallback: take first part before space
            $parts = explode(' ', $command);
            $commandName = $parts[0] ?? 'unknown';
        }

        // Sanitize command name for Prometheus metrics
        $commandName = $this->sanitizeCommandName($commandName);

        return $commandName ?: 'unknown';
    }

    protected function sanitizeCommandName(string $commandName): string
    {
        // Replace colons with underscores to avoid Redis key parsing issues
        // Replace other invalid characters with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $commandName);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized ?? '');

        // Remove leading/trailing underscores
        $sanitized = trim($sanitized ?? '', '_');

        // Limit length to reasonable size
        return substr($sanitized, 0, 50);
    }

    protected function getCommandStatus(int $exitCode): string
    {
        return match ($exitCode) {
            0 => 'success',
            1 => 'error',
            2 => 'invalid_usage',
            130 => 'interrupted',
            default => 'unknown'
        };
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.command.enabled', true);
    }
}
