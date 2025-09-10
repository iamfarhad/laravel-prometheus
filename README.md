# 🚀 Laravel Prometheus Metrics Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iamfarhad/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-prometheus)
[![Total Downloads](https://img.shields.io/packagist/dt/iamfarhad/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-prometheus)
[![PHP Version](https://img.shields.io/packagist/php-v/iamfarhad/laravel-prometheus.svg?style=flat-square)](https://packagist.org/packages/iamfarhad/laravel-prometheus)
[![Laravel Version](https://img.shields.io/badge/laravel-10%2C%2011%2C%2012-red.svg?style=flat-square)](https://laravel.com)
[![GitHub Tests](https://img.shields.io/github/actions/workflow/status/iamfarhad/laravel-prometheus/ci.yml?branch=1.x&label=tests&style=flat-square)](https://github.com/iamfarhad/laravel-prometheus/actions)

**Enterprise-grade Prometheus metrics exporter for Laravel applications with comprehensive monitoring capabilities and industry-standard SLO tracking.**

Built on the official [PromPHP/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php) library for maximum compatibility and performance.

## ✨ Key Features

- 🎯 **Complete Metric Types**: Counter, Gauge, Histogram, and Summary metrics with percentile tracking
- 📊 **Advanced Collectors**: HTTP, Database, Cache, Queue, Commands, Events, Errors, Filesystem, Mail, and Horizon
- 🏭 **Production-Ready**: Built with official PromPHP library and Laravel best practices
- 📈 **SLO Monitoring**: Industry-standard bucket configurations for p50, p95, p99, p99.9 tracking
- 💾 **Multiple Storage**: Redis, Memory, and APCu storage adapters
- 🔒 **Security First**: IP whitelisting, authentication middleware, and secure endpoint protection
- ⚡ **High Performance**: Optimized for minimal overhead with intelligent filtering
- 🧪 **Fully Tested**: Comprehensive test suite covering all components
- 📝 **Developer Friendly**: Rich documentation and intuitive API

## 📦 Installation

```bash
composer require iamfarhad/laravel-prometheus
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="Iamfarhad\Prometheus\PrometheusServiceProvider" --tag="prometheus-config"
```

### HTTP Middleware Setup

**For HTTP request tracking, you need to register the middleware. Choose one of these simple options:**

#### ✅ **Option 1: Easy Helper (Recommended)** 

**No more complex setup!** Add this single line to your `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    // Easy one-liner for HTTP metrics - automatically checks if enabled
    \Iamfarhad\Prometheus\Support\PrometheusMiddlewareHelper::register($middleware);
})
```

**Benefits:**
- ✅ **One line setup** - No complex configuration needed
- ✅ **Automatic checks** - Only registers if HTTP collector is enabled  
- ✅ **Laravel 11+ optimized** - Works perfectly with new bootstrap structure
- ✅ **Future-proof** - Handles configuration changes automatically

#### ✅ **Option 2: Manual Registration**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class);
})
```

#### ✅ **Option 3: Automatic Registration (Experimental)**

Enable automatic middleware registration in your `.env`:

```bash
PROMETHEUS_MIDDLEWARE_AUTO_REGISTER=true
```

**Note**: Automatic registration attempts to register middleware programmatically. If it doesn't work in your setup, use Options 1 or 2.

#### ⚡ **Option 4: Route-Specific Registration (Performance Optimized)**

For **high-performance applications** where you want to monitor only specific routes:

**Route Groups:**
```php
// In routes/api.php
Route::middleware([\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class])
    ->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/analytics/{id}', [AnalyticsController::class, 'show']);
    });
```

**Individual Routes:**
```php
// Monitor only critical API endpoints
Route::get('/api/orders', [OrderController::class, 'index'])
    ->middleware(\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class);

Route::post('/api/payments', [PaymentController::class, 'process'])
    ->middleware(\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class);
```

**Route Groups with Aliases:**
```php
// In bootstrap/app.php - register middleware alias
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'prometheus' => \Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class,
    ]);
})

// In routes/api.php - use the alias
Route::middleware(['prometheus'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('orders', OrderController::class);
});
```

**Controller-Level Registration:**
```php
<?php

namespace App\Http\Controllers;

use Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware;

class ApiController extends Controller
{
    public function __construct()
    {
        // Apply metrics to all methods in this controller
        $this->middleware(PrometheusMetricsMiddleware::class);
    }
}
```

**Conditional Route Monitoring:**
```php
// Monitor only in production or specific environments
Route::middleware(app()->environment('production') 
    ? [\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class] 
    : []
)->group(function () {
    Route::apiResource('analytics', AnalyticsController::class);
});
```

**Benefits of Route-Specific Registration:**
- ✅ **Performance**: Monitor only critical endpoints
- ✅ **Selective Monitoring**: Focus on business-critical routes
- ✅ **Resource Efficiency**: Reduce monitoring overhead
- ✅ **Environment Control**: Different monitoring per environment
- ✅ **Granular Control**: Fine-tune monitoring scope

**Use Cases:**
- **High-traffic applications** with performance requirements
- **API endpoints** where you want selective monitoring
- **Admin routes** that need separate tracking
- **Public vs authenticated** route monitoring
- **Critical business logic** routes only

## 🚀 Quick Start

### Basic Usage

```php
use Iamfarhad\Prometheus\Facades\Prometheus;

// Register and use a counter
$counter = Prometheus::getOrRegisterCounter('orders_total', 'Total number of orders', ['status']);
$counter->inc(['completed']);

// Register and use a histogram for response times
$histogram = Prometheus::getOrRegisterHistogram(
    'api_response_time', 
    'API response time in seconds', 
    ['endpoint', 'method'],
    [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0] // Industry-standard buckets
);
$histogram->observe(0.45, ['api/users', 'GET']);

// Register and use a summary for percentile tracking
$summary = Prometheus::getOrRegisterSummary(
    'request_size_bytes',
    'Request size summary with quantiles',
    ['content_type'],
    600, // 10 minutes max age
    [0.5, 0.95, 0.99, 0.999] // p50, p95, p99, p99.9
);
$summary->observe(1024, ['application/json']);
```

### Controller Integration

```php
<?php

namespace App\Http\Controllers;

use Iamfarhad\Prometheus\Facades\Prometheus;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function createOrder(Request $request)
    {
        $startTime = microtime(true);

        try {
            $order = Order::create($request->validated());
            
            // Record success metrics
            Prometheus::getOrRegisterCounter('orders_created_total', 'Orders created', ['status'])
                ->inc(['success']);
            
            // Record processing time
            Prometheus::getOrRegisterHistogram('order_processing_time', 'Order processing duration', ['type'])
                ->observe(microtime(true) - $startTime, ['express']);
            
            return response()->json($order, 201);
        } catch (\Exception $e) {
            // Record error metrics
            Prometheus::getOrRegisterCounter('orders_created_total', 'Orders created', ['status'])
                ->inc(['error']);
            throw $e;
        }
    }
}
```

## 📊 Built-in Collectors

### 🌐 HTTP Request Collector

**Automatically enabled** - Tracks all HTTP requests with comprehensive metrics.

**Metrics:**
- `http_requests_total{method, route, status}` - Total HTTP requests counter
- `http_request_duration_seconds{method, route}` - Response time histogram 
- `http_request_duration_seconds_summary{method, route}` - Response time percentiles
- `http_request_size_bytes{method, route}` - Request payload size
- `http_response_size_bytes{method, route, status}` - Response payload size

**Industry-Standard Configuration:**
```php
'http' => [
    'enabled' => env('PROMETHEUS_COLLECTOR_HTTP_ENABLED', true),
    
    // SLO-optimized buckets covering p50 (~100ms) to p99.9 (~2.5s)
    'histogram_buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
    'summary_quantiles' => [0.5, 0.95, 0.99, 0.999],
    'summary_max_age' => 600,
    
    // Size tracking from 1KB to 16MB
    'size_buckets' => [1024, 4096, 16384, 65536, 262144, 1048576, 4194304, 16777216],
],
```

**Example Metrics:**
```prometheus
# HTTP request rate and latency
http_requests_total{method="GET",route="api/users",status="200"} 1250
http_request_duration_seconds_bucket{method="GET",route="api/users",le="0.1"} 1180
http_request_duration_seconds_summary{method="GET",route="api/users",quantile="0.95"} 0.085
http_request_size_bytes_bucket{method="POST",route="api/orders",le="4096"} 89
```

### 🗄️ Database Query Collector

**Automatically enabled** - Monitors database performance with operation-level granularity.

**Metrics:**
- `database_queries_total{connection, table, operation}` - Query count by operation type
- `database_query_duration_seconds{connection, table, operation}` - Query duration histogram
- `database_query_duration_seconds_summary{connection, table, operation}` - Query duration percentiles

**Supported Operations:** `select`, `insert`, `update`, `delete`, `create`, `drop`, `alter`, `truncate`

**Fine-Grained Configuration:**
```php
'database' => [
    'enabled' => env('PROMETHEUS_COLLECTOR_DATABASE_ENABLED', true),
    
    // Sub-millisecond precision for fast queries to 5s for complex operations
    'histogram_buckets' => [0.0005, 0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
    'summary_quantiles' => [0.5, 0.95, 0.99, 0.999],
    'summary_max_age' => 600,
],
```

**Example Metrics:**
```prometheus
# Database performance tracking
database_queries_total{connection="mysql",table="users",operation="select"} 1250
database_queries_total{connection="mysql",table="orders",operation="insert"} 89
database_query_duration_seconds_summary{connection="mysql",table="users",operation="select",quantile="0.95"} 0.0025
```

### ⚡ Cache Operation Collector

**Disabled by default** - Monitors application cache operations (not internal storage).

⚠️ **Important**: Disabled by default to prevent recursive counting of Prometheus's own Redis operations.

**Metrics:**
- `cache_operations_total{store, operation, result}` - Cache operation counts
- `cache_operation_duration_seconds{store, operation}` - Operation duration
- `cache_operation_duration_seconds_summary{store, operation}` - Duration percentiles

**Operations:** `get`, `put`, `forget`, `flush`  
**Results:** `hit`, `miss`, `success`, `failure`

**Intelligent Filtering:**
```php
'cache' => [
    'enabled' => env('PROMETHEUS_COLLECTOR_CACHE_ENABLED', false), // Disabled by default
    
    // Sub-millisecond precision for cache operations
    'histogram_buckets' => [0.0001, 0.0005, 0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1],
    'summary_quantiles' => [0.5, 0.95, 0.99],
    'summary_max_age' => 300, // 5 minutes for frequent operations
],
```

**Enable for Application Cache:**
```bash
PROMETHEUS_COLLECTOR_CACHE_ENABLED=true
```

### 🔄 Queue Job Collectors

#### Basic Queue Collector

**Automatically enabled** - Tracks queue job processing and performance.

**Metrics:**
- `queue_jobs_total{queue, connection, status, job_class}` - Processed jobs count
- `queue_job_duration_seconds{queue, job_class}` - Job processing time histogram
- `queue_job_duration_seconds_summary{queue, job_class}` - Processing time percentiles
- `queue_active_jobs{queue, connection}` - Currently active jobs gauge

**Status Values:** `completed`, `failed`, `exception`, `timeout`

#### Enhanced Queue Collector

**Advanced monitoring** with comprehensive queue health metrics:

**Additional Metrics:**
- `queue_job_wait_time_seconds{queue, job_class}` - Time jobs wait before processing
- `queue_job_retries_total{queue, job_class, reason}` - Job retry attempts
- `queue_job_timeouts_total{queue, job_class}` - Job timeout occurrences
- `queue_size{queue, connection, status}` - Queue depth metrics
- `queue_pending_jobs{queue, connection}` - Jobs waiting to be processed
- `queue_failed_jobs{queue, connection}` - Failed jobs count
- `queue_workers{queue, connection, supervisor}` - Active worker processes

**Configuration:**
```php
'queue' => [
    'enabled' => env('PROMETHEUS_COLLECTOR_QUEUE_ENABLED', true),
    
    // Wide range from quick jobs to long-running processes (10 minutes)
    'histogram_buckets' => [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 300.0, 600.0],
    'summary_quantiles' => [0.5, 0.95, 0.99],
    'summary_max_age' => 900, // 15 minutes for long-running jobs
],
```

### 🎯 Command Collector

**Automatically enabled** - Tracks Laravel Artisan command executions.

**Metrics:**
- `artisan_commands_total{command, status}` - Command execution count
- `artisan_command_duration_seconds{command, status}` - Execution time histogram

**Status Values:** `success`, `error`, `invalid_usage`, `interrupted`, `unknown`

**Configuration:**
```php
'command' => [
    'enabled' => env('PROMETHEUS_COLLECTOR_COMMAND_ENABLED', true),
    
    // From quick commands to long migrations (30 minutes)
    'histogram_buckets' => [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0, 300.0, 600.0, 1800.0],
],
```

**Example Metrics:**
```prometheus
artisan_commands_total{command="migrate",status="success"} 5
artisan_commands_total{command="queue_work",status="success"} 1
artisan_command_duration_seconds_bucket{command="migrate",le="30"} 5
```

### 🌊 Horizon Collector

**Available when Laravel Horizon is installed** - Comprehensive Horizon monitoring.

**Metrics:**
- `horizon_supervisors{environment}` - Active supervisors count
- `horizon_workload{queue, supervisor}` - Queue workload distribution  
- `horizon_master_loops_total{environment}` - Master supervisor loops
- `queue_workers{queue, connection, supervisor}` - Worker process tracking

**Enable:**
```bash
PROMETHEUS_COLLECTOR_HORIZON_ENABLED=true
```

**Features:**
- ✅ Automatic Horizon detection
- ✅ Supervisor health monitoring
- ✅ Worker process tracking
- ✅ Workload balance analysis

### 🚨 Error Collector

**Disabled by default** - Tracks application errors and exceptions.

**Metrics:**
- `application_errors_total{exception_class, severity, component}` - Application errors
- `application_response_errors_total{http_status, method, route}` - HTTP errors
- `application_critical_errors_total{exception_class, component}` - Critical errors

### 📁 Additional Collectors

#### Event Collector
- `events_fired_total{event_class, status}` - Laravel event tracking

#### Filesystem Collector  
- `filesystem_operations_total{disk, operation, status}` - File system operations

#### Mail Collector
- `mail_sent_total{driver, status, template}` - Email delivery tracking

**Enable any collector:**
```bash
PROMETHEUS_COLLECTOR_[COLLECTOR_NAME]_ENABLED=true
```

## ⚙️ Configuration

### Storage Backends

Choose your preferred storage backend:

```php
'storage' => [
    'driver' => env('PROMETHEUS_STORAGE_DRIVER', 'redis'), // redis, memory, apcu

    'redis' => [
        'connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
        'prefix' => env('PROMETHEUS_REDIS_PREFIX', 'prometheus_'),
    ],
],
```

### Global Namespace

Set a global namespace to avoid metric name conflicts:

```php
'namespace' => env('PROMETHEUS_NAMESPACE', ''), // e.g., 'myapp'
```

Results in metrics like: `myapp_http_requests_total`

### Industry-Standard Defaults

The package comes with optimized industry-standard configurations:

```php
// Optimized for SLO monitoring
'default_histogram_buckets' => [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],

// Complete percentile coverage
'default_summary_quantiles' => [0.5, 0.95, 0.99, 0.999],

// Balanced for real-time monitoring
'default_summary_max_age' => 600, // 10 minutes
```

## 🔒 Security

### Protecting the Metrics Endpoint

The package automatically registers a `/metrics` endpoint with configurable security:

```php
'metrics_route' => [
    'enabled' => env('PROMETHEUS_METRICS_ROUTE_ENABLED', true),
    'path' => env('PROMETHEUS_METRICS_PATH', '/metrics'),
    'middleware' => [], // Add security middleware
],
```

#### IP Address Restriction

Use the built-in `AllowIps` middleware:

```php
'metrics_route' => [
    'middleware' => [\Iamfarhad\Prometheus\Http\Middleware\AllowIps::class],
],
```

**Environment Configuration:**
```bash
# Supports CIDR notation and multiple IPs
PROMETHEUS_ALLOWED_IPS=127.0.0.1,192.168.1.0/24,10.0.0.100
```

**Features:**
- ✅ IPv4 and IPv6 support
- ✅ CIDR notation support
- ✅ Multiple IP ranges
- ✅ Returns 403 for unauthorized access

#### Authentication

Add authentication middleware:

```php
'metrics_route' => [
    'middleware' => ['auth.basic', 'throttle:60,1'],
],
```

#### Combined Security

For maximum security:

```php
'metrics_route' => [
    'middleware' => [
        \Iamfarhad\Prometheus\Http\Middleware\AllowIps::class,
        'auth.basic',
        'throttle:60,1',
    ],
],
```

## 🔧 Advanced Usage

### Custom Metrics with Summary Support

```php
use Iamfarhad\Prometheus\Facades\Prometheus;

// Counter with custom labels
$counter = Prometheus::getOrRegisterCounter(
    'user_actions_total',
    'Total user actions',
    ['action_type', 'source']
);
$counter->inc(['login', 'web']);

// Histogram with industry-standard buckets
$histogram = Prometheus::getOrRegisterHistogram(
    'api_response_time',
    'API response time distribution', 
    ['endpoint', 'method'],
    [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
);
$histogram->observe(0.075, ['users', 'GET']);

// Summary with percentile tracking
$summary = Prometheus::getOrRegisterSummary(
    'request_processing_time',
    'Request processing time summary',
    ['service', 'endpoint'],
    600, // 10 minutes max age
    [0.5, 0.95, 0.99, 0.999] // p50, p95, p99, p99.9
);
$summary->observe(0.125, ['auth', 'login']);

// Gauge for real-time values
$gauge = Prometheus::getOrRegisterGauge(
    'active_connections',
    'Number of active connections',
    ['service']
);
$gauge->set(42, ['websocket']);
```

### Middleware Integration

Create custom middleware for application-specific metrics:

```php
<?php

namespace App\Http\Middleware;

use Iamfarhad\Prometheus\Facades\Prometheus;
use Closure;

class MetricsMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        // Record custom business metrics
        Prometheus::getOrRegisterHistogram(
            'business_operation_duration',
            'Business operation processing time',
            ['operation', 'user_type']
        )->observe(
            microtime(true) - $startTime,
            [$request->route()->getName(), $request->user()?->type ?? 'guest']
        );
        
        return $response;
    }
}
```

## 📈 Monitoring & Alerting

### Prometheus Configuration

Add this scrape configuration to your `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'laravel-app'
    static_configs:
      - targets: ['your-app.com:80']
    metrics_path: '/metrics'
    scrape_interval: 15s
    scrape_timeout: 10s
    basic_auth:
      username: 'prometheus'
      password: 'your-password'
```

### Essential Queries

#### SLO Monitoring
```promql
# Request success rate (SLI)
sum(rate(http_requests_total{status!~"5.."}[5m])) / sum(rate(http_requests_total[5m]))

# 95th percentile response time
histogram_quantile(0.95, sum(rate(http_request_duration_seconds_bucket[5m])) by (le))

# Error rate
sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))
```

#### Database Performance
```promql
# Slow query detection (p99 > 1s)
histogram_quantile(0.99, sum(rate(database_query_duration_seconds_bucket[5m])) by (le, table, operation)) > 1

# Query rate by operation
sum(rate(database_queries_total[5m])) by (operation)

# Top slowest tables
topk(5, avg(rate(database_query_duration_seconds_sum[5m]) / rate(database_query_duration_seconds_count[5m])) by (table))
```

#### Queue Health
```promql
# Queue processing rate
sum(rate(queue_jobs_total{status="completed"}[5m])) by (queue)

# Failed job rate
sum(rate(queue_jobs_total{status="failed"}[5m])) / sum(rate(queue_jobs_total[5m]))

# Queue depth
sum(queue_pending_jobs) by (queue)
```

### Grafana Dashboard

Create comprehensive dashboards with:

**Panels:**
- HTTP request rate and latency percentiles
- Database query performance by operation
- Queue processing metrics and health
- Error rates and exception tracking
- Command execution monitoring

**Alerts:**
- High error rate (> 1%)
- Slow database queries (p95 > 500ms)
- Queue depth growth
- Failed job rate increase

## 🧪 Testing

### Test Suite Coverage

The package includes comprehensive tests:

- ✅ **Unit Tests**: All metric types and collectors
- ✅ **Feature Tests**: HTTP endpoints and middleware
- ✅ **Integration Tests**: Storage adapters and PromPHP integration
- ✅ **Security Tests**: IP filtering and authentication

### Testing Custom Metrics

```php
use Iamfarhad\Prometheus\Tests\TestCase;

class CustomMetricsTest extends TestCase
{
    public function test_custom_counter_increments()
    {
        $counter = Prometheus::getOrRegisterCounter('test_counter', 'Test counter', ['type']);
        $counter->inc(['test']);
        $counter->inc(['test']);

        $metrics = Prometheus::collect();
        $this->assertStringContains('test_counter{type="test"} 2', Prometheus::render());
    }

    public function test_summary_tracks_percentiles()
    {
        $summary = Prometheus::getOrRegisterSummary(
            'test_summary',
            'Test summary',
            ['method'],
            300,
            [0.5, 0.95, 0.99]
        );
        
        // Add sample observations
        for ($i = 0; $i < 100; $i++) {
            $summary->observe($i / 100, ['GET']);
        }

        $rendered = Prometheus::render();
        $this->assertStringContains('test_summary{method="GET",quantile="0.5"}', $rendered);
        $this->assertStringContains('test_summary{method="GET",quantile="0.95"}', $rendered);
    }
}
```

## 🚀 Performance Optimization

### Best Practices

1. **Enable Only Needed Collectors**
   ```bash
   # Disable unused collectors
   PROMETHEUS_COLLECTOR_CACHE_ENABLED=false
   PROMETHEUS_COLLECTOR_EVENTS_ENABLED=false
   PROMETHEUS_COLLECTOR_FILESYSTEM_ENABLED=false
   ```

2. **Optimize Storage Backend**
   ```bash
   # Use Redis for production
   PROMETHEUS_STORAGE_DRIVER=redis
   ```

3. **Configure Appropriate Buckets**
   ```php
   // Tailor buckets to your use case
   'histogram_buckets' => [0.01, 0.05, 0.1, 0.5, 1.0, 2.5, 5.0],
   ```

4. **Monitor Resource Usage**
   - Each enabled collector adds minimal overhead (~0.1ms per request)
   - Summary metrics use more memory than histograms
   - High-cardinality labels increase storage requirements

5. **Choose the Right Middleware Strategy**
   ```bash
   # For most applications (recommended)
   Use Option 1: PrometheusMiddlewareHelper::register()
   
   # For high-performance apps with 1000+ RPS
   Use Option 4: Route-specific registration
   
   # For testing/experimental setups
   Use Option 3: Automatic registration
   ```

### Middleware Performance Guide

**Choose your middleware registration strategy based on your application requirements:**

| **Application Type** | **Recommended Option** | **Reasoning** |
|---------------------|----------------------|---------------|
| **Standard Web/API** | Helper Registration (Option 1) | Easy setup, automatic checks, minimal overhead |
| **High-Traffic API** (1000+ RPS) | Route-Specific (Option 4) | Monitor only critical endpoints, reduce overhead |
| **Microservices** | Route-Specific (Option 4) | Focus on service-specific endpoints |
| **Admin Dashboards** | Global Registration (Option 1/2) | Monitor all admin actions |
| **Public APIs** | Route-Specific (Option 4) | Monitor API endpoints, skip static assets |
| **Development/Testing** | Auto Registration (Option 3) | Zero-config for quick testing |

**Performance Impact Comparison:**

```php
// Global Registration (All Routes)
// ✅ Complete monitoring coverage
// ⚠️  ~0.1ms overhead per request
// 📊 Full application metrics
Route::middleware(['prometheus'])->group(function () {
    // All routes monitored
});

// Route-Specific Registration (Critical Routes Only)
// ✅ Minimal performance impact
// ✅ Focus on business-critical metrics
// ⚠️  Partial monitoring coverage
Route::middleware(['prometheus'])->group(function () {
    Route::post('/api/orders', [OrderController::class, 'store']);      // Monitor
    Route::post('/api/payments', [PaymentController::class, 'process']); // Monitor
});
Route::get('/health', [HealthController::class, 'check']); // Skip monitoring

// Smart Conditional Registration
// ✅ Environment-specific monitoring
// ✅ Zero production overhead for non-critical routes
Route::middleware(config('app.env') === 'production' 
    ? ['prometheus'] 
    : []
)->group(function () {
    // Production: monitored, Development: not monitored
});
```

**Real-World Example:**

```php
// routes/api.php - Production-optimized monitoring setup

// Critical business endpoints - Always monitor
Route::middleware([\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::post('/payments', [PaymentController::class, 'process']);
        Route::get('/analytics/sales', [AnalyticsController::class, 'sales']);
    });

// Admin endpoints - Monitor in production only
Route::middleware(app()->environment('production') 
    ? [\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class] 
    : []
)->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::post('/users', [UserController::class, 'store']);
});

// Health/Status endpoints - No monitoring needed (reduces noise)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/status', [StatusController::class, 'check']);

// Public API - Selective monitoring for rate limiting insights
Route::prefix('public-api')->group(function () {
    Route::middleware([\Iamfarhad\Prometheus\Http\Middleware\PrometheusMetricsMiddleware::class])
        ->get('/search', [SearchController::class, 'search']); // Monitor
    
    Route::get('/docs', [DocsController::class, 'index']); // Don't monitor
});
```

### Memory Optimization

```php
// Reduce Summary max age for high-traffic applications
'summary_max_age' => 300, // 5 minutes instead of 10

// Use fewer quantiles for less critical metrics
'summary_quantiles' => [0.5, 0.95], // Instead of [0.5, 0.95, 0.99, 0.999]
```

## 🔧 Troubleshooting

### Common Issues

1. **Cache Collector Showing False Metrics**
   - **Solution**: Cache collector is disabled by default to prevent counting Prometheus's own Redis operations
   - **Enable only for application cache**: Ensure you're using dedicated cache stores

2. **High Memory Usage**
   - Use Redis storage instead of Memory
   - Reduce histogram bucket count
   - Optimize Summary quantiles and max age

3. **Metrics Not Appearing**
   - Check collector configuration in `config/prometheus.php`
   - Verify storage backend connectivity
   - Check Laravel logs for errors

4. **Slow Response Times**
   - Optimize histogram bucket configuration
   - Use Redis with proper connection pooling
   - Consider metric sampling for very high traffic

### Debug Mode

Enable comprehensive debug logging to troubleshoot metrics collection:

```bash
# Add to .env for debugging
PROMETHEUS_DEBUG=true
```

**Debug Output Examples:**
```
[Prometheus] Prometheus instance created {"namespace":"","registry_class":"Prometheus\\CollectorRegistry"}
[Prometheus] Metric operation: getOrRegisterCounter on http_requests_total {"operation":"getOrRegisterCounter","metric":"http_requests_total","labels":["method","route","status"]}
[Prometheus] Collector HttpRequestCollector: recording request {"method":"GET","route":"api/users","status":200}
[Prometheus] Performance: metrics collection took 15.2ms {"samples_count":12,"duration_ms":15.2}
[Prometheus] Performance: metrics rendering took 8.7ms {"output_size":32451,"duration_ms":8.7}
```

**Debug Features:**
- ✅ **Metric Operations**: All metric registrations and operations
- ✅ **Collector Activity**: Detailed collector behavior tracking  
- ✅ **Performance Timing**: Collection and rendering performance
- ✅ **Storage Operations**: Redis/storage adapter interactions
- ✅ **Request Tracking**: HTTP middleware execution flow
- ✅ **Cache Filtering**: Cache operation filtering logic

**Production Note**: Always disable debug mode in production to avoid log bloat.

### Middleware Configuration

All available environment variables for easy configuration:

```bash
# Core Configuration
PROMETHEUS_ENABLED=true
PROMETHEUS_NAMESPACE=myapp
PROMETHEUS_STORAGE_DRIVER=redis
PROMETHEUS_DEBUG=false

# Middleware Configuration
PROMETHEUS_MIDDLEWARE_AUTO_REGISTER=false  # Experimental automatic registration

# Collector Settings
PROMETHEUS_COLLECTOR_HTTP_ENABLED=true
PROMETHEUS_COLLECTOR_DATABASE_ENABLED=true
PROMETHEUS_COLLECTOR_COMMAND_ENABLED=true
PROMETHEUS_COLLECTOR_CACHE_ENABLED=false   # Disabled by default
PROMETHEUS_COLLECTOR_QUEUE_ENABLED=true
PROMETHEUS_COLLECTOR_ERRORS_ENABLED=false
PROMETHEUS_COLLECTOR_EVENTS_ENABLED=false
PROMETHEUS_COLLECTOR_FILESYSTEM_ENABLED=false
PROMETHEUS_COLLECTOR_MAIL_ENABLED=false
PROMETHEUS_COLLECTOR_HORIZON_ENABLED=false

# Metrics Route
PROMETHEUS_METRICS_ROUTE_ENABLED=true
PROMETHEUS_METRICS_PATH=/metrics

# Security
PROMETHEUS_ALLOWED_IPS=127.0.0.1,192.168.1.0/24

# Storage Configuration
PROMETHEUS_REDIS_CONNECTION=default
PROMETHEUS_REDIS_PREFIX=metrics_
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/iamfarhad/laravel-prometheus.git
cd laravel-prometheus
composer install

# Run tests
composer test

# Check code style
composer format

# Run static analysis
composer analyse
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🌟 Support

- 📖 **Documentation**: [Laravel Prometheus Docs](https://laravel-prometheus.com)
- 🐛 **Issues**: [GitHub Issues](https://github.com/iamfarhad/laravel-prometheus/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/iamfarhad/laravel-prometheus/discussions)
- ⭐ **Star on GitHub**: [Show your support](https://github.com/iamfarhad/laravel-prometheus)

## 🏆 Credits

- **Author**: [Farhad Zand](https://github.com/iamfarhad)
- **Built on**: [PromPHP/prometheus_client_php](https://github.com/PromPHP/prometheus_client_php)
- **Inspired by**: Prometheus community best practices and Laravel ecosystem

---

**🚀 Built with ❤️ for the Laravel community**

*Ready for enterprise-grade monitoring with industry-standard SLO tracking!* ⭐

## 🎯 Key Differentiators

- ✅ **Official PromPHP Integration**: Built on the official PHP client
- ✅ **Industry-Standard Buckets**: Optimized for real-world SLO monitoring
- ✅ **Complete Metric Types**: Counter, Gauge, Histogram, AND Summary with percentiles
- ✅ **Intelligent Filtering**: Prevents recursive metric counting issues
- ✅ **Security-First**: Built-in IP filtering and authentication support
- ✅ **Production-Ready**: Used in enterprise Laravel applications
- ✅ **Comprehensive Testing**: Extensive test coverage for reliability
- ✅ **Developer-Friendly**: Rich documentation and intuitive API
