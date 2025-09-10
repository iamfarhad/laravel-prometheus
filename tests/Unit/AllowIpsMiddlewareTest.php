<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Tests\Unit;

use Iamfarhad\Prometheus\Http\Middleware\AllowIps;
use Iamfarhad\Prometheus\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AllowIpsMiddlewareTest extends TestCase
{
    private AllowIps $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AllowIps;
    }

    public function test_allows_request_when_no_ips_configured(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => []]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_allows_request_from_allowed_single_ip(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['192.168.1.100']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_allows_request_from_allowed_cidr_range(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['192.168.1.0/24']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.50');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_allows_localhost(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['127.0.0.1']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_blocks_request_from_disallowed_ip(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['192.168.1.100']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('Forbidden', $response->getContent());
    }

    public function test_blocks_request_from_ip_outside_cidr_range(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['192.168.1.0/24']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.2.50');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('Forbidden', $response->getContent());
    }

    public function test_allows_request_from_multiple_allowed_ips(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['192.168.1.100', '10.0.0.1', '172.16.0.0/16']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '172.16.5.10');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_allows_ipv6_localhost(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['::1']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '::1');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_blocks_ipv6_when_not_allowed(): void
    {
        // Arrange
        config(['prometheus.security.allowed_ips' => ['127.0.0.1']]);
        $request = Request::create('/metrics', 'GET');
        $request->server->set('REMOTE_ADDR', '2001:db8::1');

        // Act
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK', 200);
        });

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('Forbidden', $response->getContent());
    }
}
