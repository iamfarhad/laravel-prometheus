<?php

declare(strict_types=1);

use Iamfarhad\Prometheus\Http\Controllers\PrometheusController;
use Illuminate\Support\Facades\Route;

Route::get(config('prometheus.metrics_route.path', '/metrics'), [PrometheusController::class, 'metrics'])
    ->middleware(config('prometheus.metrics_route.middleware', []))
    ->name('prometheus.metrics');
