<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;

final class AllowIps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $allowedIps = config('prometheus.security.allowed_ips', []);

        // If no IPs are configured, allow all (for development)
        if (empty($allowedIps)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        // Check if client IP is in the allowed list
        if (! $this->isIpAllowed($clientIp, $allowedIps)) {
            return response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Check if the given IP is allowed.
     */
    private function isIpAllowed(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowedIp) {
            // Support CIDR notation (e.g., 192.168.1.0/24) and single IPs
            if (IpUtils::checkIp($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }
}
