<?php

declare(strict_types=1);

/**
 * Laravel Prometheus Package - Basic Usage Examples
 *
 * This file demonstrates common usage patterns for the Laravel Prometheus package.
 */

use Iamfarhad\Prometheus\Facades\Prometheus;

// =============================================================================
// 1. BASIC COUNTER USAGE
// =============================================================================

/**
 * Register a simple counter for tracking events
 */
$counter = Prometheus::registerCounter(
    'user_registrations_total',
    'Total number of user registrations'
);

// Increment the counter
$counter->inc();

// Increment by specific value
$counter->incBy(5);

// =============================================================================
// 2. COUNTER WITH LABELS
// =============================================================================

/**
 * Register a counter with labels for categorizing events
 */
$labeledCounter = Prometheus::registerCounter(
    'orders_total',
    'Total number of orders',
    ['status', 'payment_method']
);

// Increment with label values
$labeledCounter->inc(['status' => 'completed', 'payment_method' => 'credit_card']);
$labeledCounter->inc(['status' => 'pending', 'payment_method' => 'paypal']);
$labeledCounter->inc(['status' => 'completed', 'payment_method' => 'paypal']);

// =============================================================================
// 3. GAUGE USAGE
// =============================================================================

/**
 * Register a gauge for tracking values that can go up or down
 */
$gauge = Prometheus::registerGauge(
    'active_users',
    'Current number of active users'
);

// Set the gauge value
$gauge->set(150);

// Increment/decrement
$gauge->inc();    // Now 151
$gauge->dec();    // Now 150
$gauge->incBy(10); // Now 160
$gauge->decBy(5);  // Now 155

// =============================================================================
// 4. HISTOGRAM USAGE
// =============================================================================

/**
 * Register a histogram for tracking value distributions
 */
$histogram = Prometheus::registerHistogram(
    'request_duration_seconds',
    'HTTP request duration in seconds',
    ['method', 'endpoint'],
    [0.1, 0.25, 0.5, 1.0, 2.5, 5.0] // Custom buckets
);

// Record observations
$histogram->observe(0.15, ['method' => 'GET', 'endpoint' => '/api/users']);
$histogram->observe(0.45, ['method' => 'POST', 'endpoint' => '/api/users']);
$histogram->observe(2.1, ['method' => 'GET', 'endpoint' => '/api/reports']);

// =============================================================================
// 5. SUMMARY USAGE
// =============================================================================

/**
 * Register a summary for tracking quantiles over time
 */
$summary = Prometheus::registerSummary(
    'api_response_time',
    'API response time with quantiles',
    ['service'],
    [0.5, 0.9, 0.95, 0.99] // Quantiles to track
);

// Record values
$summary->observe(0.12, ['service' => 'user-service']);
$summary->observe(0.34, ['service' => 'order-service']);
$summary->observe(1.2, ['service' => 'user-service']);

// =============================================================================
// 6. USING METRICS IN LARAVEL APPLICATION CODE
// =============================================================================

/**
 * Example controller showing real-world usage
 */
class OrderController
{
    public function store(Request $request)
    {
        $startTime = microtime(true);

        try {
            // Business logic
            $order = Order::create($request->validated());

            // Record successful order
            Prometheus::counter('orders_created_total')->inc([
                'status' => 'success',
                'source' => 'api',
            ]);

            // Record order value
            Prometheus::histogram('order_value')->observe(
                $order->total,
                ['currency' => $order->currency]
            );

            // Record processing time
            $processingTime = microtime(true) - $startTime;
            Prometheus::histogram('order_processing_time')->observe(
                $processingTime,
                ['step' => 'creation']
            );

            return response()->json(['order' => $order], 201);
        } catch (Exception $e) {
            // Record failed order
            Prometheus::counter('orders_created_total')->inc([
                'status' => 'failed',
                'source' => 'api',
                'error_type' => get_class($e),
            ]);

            throw $e;
        }
    }
}

/**
 * Example service showing metric usage
 */
class PaymentService
{
    public function processPayment(Order $order, array $paymentData): bool
    {
        $startTime = microtime(true);

        // Update active payments gauge
        Prometheus::gauge('active_payments')->inc();

        try {
            // Payment processing logic
            $result = $this->gateway->charge($paymentData);

            if ($result->isSuccessful()) {
                Prometheus::counter('payments_total')->inc([
                    'status' => 'success',
                    'gateway' => $paymentData['gateway'],
                ]);

                return true;
            } else {
                Prometheus::counter('payments_total')->inc([
                    'status' => 'failed',
                    'gateway' => $paymentData['gateway'],
                    'reason' => $result->getErrorCode(),
                ]);

                return false;
            }
        } finally {
            // Always decrement the active payments gauge
            Prometheus::gauge('active_payments')->dec();

            // Record processing time
            $processingTime = microtime(true) - $startTime;
            Prometheus::histogram('payment_processing_time')->observe(
                $processingTime,
                ['gateway' => $paymentData['gateway']]
            );
        }
    }
}

/**
 * Example job showing queue metrics
 */
class ProcessOrderJob
{
    public function handle()
    {
        $startTime = microtime(true);

        Prometheus::gauge('queue_active_jobs')->inc(['queue' => 'orders']);

        try {
            // Job processing logic
            $this->processOrder($this->orderId);

            Prometheus::counter('queue_jobs_total')->inc([
                'queue' => 'orders',
                'status' => 'completed',
                'job_class' => self::class,
            ]);
        } catch (Exception $e) {
            Prometheus::counter('queue_jobs_total')->inc([
                'queue' => 'orders',
                'status' => 'failed',
                'job_class' => self::class,
                'error_type' => get_class($e),
            ]);

            throw $e;
        } finally {
            Prometheus::gauge('queue_active_jobs')->dec(['queue' => 'orders']);

            $processingTime = microtime(true) - $startTime;
            Prometheus::histogram('queue_job_duration_seconds')->observe(
                $processingTime,
                ['queue' => 'orders', 'job_class' => self::class]
            );
        }
    }
}

/**
 * Example middleware for collecting HTTP metrics
 */
class PrometheusMetricsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('prometheus.collectors.http.enabled')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        $route = $request->route()?->getName() ?: $request->path();

        // Record request metrics
        Prometheus::counter('http_requests_total')->inc([
            'method' => $request->method(),
            'route' => $route,
            'status' => (string) $response->getStatusCode(),
        ]);

        Prometheus::histogram('http_request_duration_seconds')->observe(
            $duration,
            ['method' => $request->method(), 'route' => $route]
        );

        return $response;
    }
}

// =============================================================================
// 7. COLLECTING AND EXPORTING METRICS
// =============================================================================

/**
 * Collect all metrics data
 */
$metricsData = Prometheus::collect();

/**
 * Render metrics in Prometheus format
 */
$prometheusOutput = Prometheus::render();

// The output will look like:
// # HELP orders_created_total Total number of orders
// # TYPE orders_created_total counter
// orders_created_total{status="success",source="api"} 150
// orders_created_total{status="failed",source="api",error_type="ValidationException"} 5

/**
 * Check if a metric exists
 */
if (Prometheus::hasMetric('orders_created_total')) {
    // Metric exists, safe to use
    Prometheus::counter('orders_created_total')->inc();
}

/**
 * Clear all metrics (useful for testing)
 */
Prometheus::clear();
