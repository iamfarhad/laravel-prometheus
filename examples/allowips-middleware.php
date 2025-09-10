<?php

declare(strict_types=1);

/**
 * Example: Using AllowIps Middleware for Metrics Endpoint Security
 *
 * This example demonstrates how to configure IP-based access control
 * for the Prometheus metrics endpoint.
 */

// 1. Configure in config/prometheus.php
return [
    'metrics_route' => [
        'enabled' => true,
        'path' => '/metrics',
        'middleware' => [
            \Iamfarhad\Prometheus\Http\Middleware\AllowIps::class,
        ],
    ],

    'security' => [
        'allowed_ips' => [
            '127.0.0.1',              // Allow localhost
            '192.168.1.0/24',         // Allow local network
            '10.0.0.100',             // Allow specific monitoring server
            '::1',                    // Allow IPv6 localhost
        ],
    ],
];

// 2. OR configure via environment variables in .env
/*
PROMETHEUS_ALLOWED_IPS=127.0.0.1,192.168.1.0/24,10.0.0.100
*/

// 3. Test access from different IPs

// ✅ Allowed IPs will receive metrics:
// curl http://localhost:8000/metrics
// Response: 200 OK with Prometheus metrics

// ❌ Blocked IPs will receive:
// curl -H "X-Forwarded-For: 203.0.113.1" http://localhost:8000/metrics
// Response: 403 Forbidden

// 4. Production setup example
return [
    'metrics_route' => [
        'middleware' => [
            \Iamfarhad\Prometheus\Http\Middleware\AllowIps::class,
            'auth.basic',  // Additional authentication
        ],
    ],

    'security' => [
        'allowed_ips' => [
            '10.0.0.0/8',             // Internal network
            '172.16.0.0/12',          // Docker networks
            '192.168.0.0/16',         // Local networks
            '203.0.113.100',          // Prometheus server IP
        ],
    ],
];

// 5. Kubernetes setup example
return [
    'security' => [
        'allowed_ips' => [
            '10.244.0.0/16',          // Pod network
            '10.96.0.0/12',           // Service network
            '127.0.0.1',              // Localhost
        ],
    ],
];

// 6. Development setup (allow all)
return [
    'security' => [
        'allowed_ips' => [],         // Empty array = allow all
    ],
];
