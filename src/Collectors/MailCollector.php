<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Throwable;

final class MailCollector implements CollectorInterface
{
    private ?Counter $mailSentCounter = null;

    private ?Counter $mailFailuresCounter = null;

    private ?Histogram $deliveryDurationHistogram = null;

    private ?Gauge $mailQueueSizeGauge = null;

    private array $sendingTimes = [];

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

        $this->mailSentCounter = $this->prometheus->getOrRegisterCounter(
            'mail_sent_total',
            'Total number of emails sent',
            ['driver', 'status', 'template', 'priority']
        );

        $this->mailFailuresCounter = $this->prometheus->getOrRegisterCounter(
            'mail_failures_total',
            'Total number of failed email deliveries',
            ['driver', 'error_type', 'template']
        );

        $this->deliveryDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'mail_delivery_duration_seconds',
            'Time spent sending emails',
            ['driver', 'template'],
            [0.1, 0.25, 0.5, 1.0, 2.0, 5.0, 10.0, 30.0, 60.0, 120.0] // Up to 2 minutes
        );

        $this->mailQueueSizeGauge = $this->prometheus->getOrRegisterGauge(
            'mail_queue_size',
            'Number of emails pending in queue',
            ['queue', 'priority']
        );
    }

    public function isEnabled(): bool
    {
        return config('prometheus.collectors.mail.enabled', false);
    }

    private function registerEventListeners(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        Event::listen(MessageSending::class, [$this, 'handleMessageSending']);
        Event::listen(MessageSent::class, [$this, 'handleMessageSent']);
        // Note: MessageSendingFailed event is not available in all Laravel versions
    }

    public function handleMessageSending(MessageSending $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $messageId = $this->getMessageId($event->message);
            $this->sendingTimes[$messageId] = microtime(true);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function handleMessageSent(MessageSent $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $messageId = $this->getMessageId($event->message);
            $driver = $this->getMailDriver();
            $template = $this->getTemplate($event->message);
            $priority = $this->getPriority($event->message);

            // Record successful send
            if ($this->mailSentCounter) {
                $this->mailSentCounter->inc([
                    'driver' => $driver,
                    'status' => 'sent',
                    'template' => $template,
                    'priority' => $priority,
                ]);
            }

            // Record delivery duration
            if ($this->deliveryDurationHistogram && isset($this->sendingTimes[$messageId])) {
                $duration = microtime(true) - $this->sendingTimes[$messageId];
                $this->deliveryDurationHistogram->observe($duration, [
                    'driver' => $driver,
                    'template' => $template,
                ]);
                unset($this->sendingTimes[$messageId]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function handleMessageSendingFailed(object $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $messageId = $this->getMessageId($event->message);
            $driver = $this->getMailDriver();
            $template = $this->getTemplate($event->message);
            $errorType = $this->getErrorType($event);

            // Record failure
            if ($this->mailFailuresCounter) {
                $this->mailFailuresCounter->inc([
                    'driver' => $driver,
                    'error_type' => $errorType,
                    'template' => $template,
                ]);
            }

            // Clean up timing data
            unset($this->sendingTimes[$messageId]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function recordQueuedMail(string $queue = 'default', string $priority = 'normal'): void
    {
        if (! $this->isEnabled() || ! $this->mailQueueSizeGauge) {
            return;
        }

        try {
            // This is a simplified implementation
            // In practice, you'd want to integrate with your queue monitoring
            $this->updateQueueSize($queue, $priority);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function recordMailTemplate(string $template, string $status = 'sent'): void
    {
        if (! $this->isEnabled() || ! $this->mailSentCounter) {
            return;
        }

        try {
            $this->mailSentCounter->inc([
                'driver' => $this->getMailDriver(),
                'status' => $status,
                'template' => $template,
                'priority' => 'normal',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function getMessageId(mixed $message): string
    {
        try {
            // Try to get message ID from headers
            if (method_exists($message, 'getHeaders')) {
                $headers = $message->getHeaders();
                if ($headers && method_exists($headers, 'get')) {
                    $messageIdHeader = $headers->get('Message-ID');
                    if ($messageIdHeader) {
                        return $messageIdHeader->getFieldBody();
                    }
                }
            }

            // Fallback to a generated ID
            return uniqid('mail_', true);
        } catch (Throwable $e) {
            return uniqid('mail_fallback_', true);
        }
    }

    private function getMailDriver(): string
    {
        try {
            return config('mail.default', 'smtp');
        } catch (Throwable $e) {
            return 'unknown';
        }
    }

    private function getTemplate(mixed $message): string
    {
        try {
            // Try to extract template name from message
            if (method_exists($message, 'getSubject')) {
                $subject = $message->getSubject();
                if ($subject) {
                    // Sanitize subject to use as template identifier
                    $template = preg_replace('/[^a-zA-Z0-9_-]/', '_', $subject);

                    return substr($template, 0, 50);
                }
            }

            return 'unknown';
        } catch (Throwable $e) {
            return 'unknown';
        }
    }

    private function getPriority(mixed $message): string
    {
        try {
            if (method_exists($message, 'getHeaders')) {
                $headers = $message->getHeaders();
                if ($headers && method_exists($headers, 'get')) {
                    $priorityHeader = $headers->get('X-Priority');
                    if ($priorityHeader) {
                        $priority = $priorityHeader->getFieldBody();

                        return match ($priority) {
                            '1', '2' => 'high',
                            '4', '5' => 'low',
                            default => 'normal'
                        };
                    }
                }
            }

            return 'normal';
        } catch (Throwable $e) {
            return 'normal';
        }
    }

    private function getErrorType(object $event): string
    {
        try {
            // Try to categorize the error type
            $message = $event->message ?? '';

            if (str_contains($message, 'timeout')) {
                return 'timeout';
            }

            if (str_contains($message, 'authentication')) {
                return 'auth_failed';
            }

            if (str_contains($message, 'quota') || str_contains($message, 'limit')) {
                return 'quota_exceeded';
            }

            if (str_contains($message, 'invalid') || str_contains($message, 'malformed')) {
                return 'invalid_email';
            }

            return 'unknown';
        } catch (Throwable $e) {
            return 'unknown';
        }
    }

    private function updateQueueSize(string $queue, string $priority): void
    {
        try {
            // This is a placeholder implementation
            // You'd typically integrate with your queue system to get actual queue sizes
            // For Redis queues, you could use Redis commands
            // For database queues, you could query the jobs table

            $size = $this->getQueueSize($queue);

            if ($this->mailQueueSizeGauge) {
                $this->mailQueueSizeGauge->set($size, [
                    'queue' => $queue,
                    'priority' => $priority,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function getQueueSize(string $queue): int
    {
        try {
            // Placeholder implementation - replace with actual queue size logic
            return 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}
