<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Http\Controllers;

use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PrometheusController
{
    public function __construct(private Prometheus $prometheus) {}

    public function metrics(Request $request): Response
    {
        $content = $this->prometheus->render();

        return response($content, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
