<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Contracts;

interface CollectorInterface
{
    public function registerMetrics(): void;

    public function isEnabled(): bool;
}
