<?php

declare(strict_types=1);

namespace G8scope\Core\Alerting\Strategies;

use G8scope\Core\Contracts\AlertStrategy;

/**
 * SLO burn-rate (spec §7, P3). burn = observed_error_rate / error_budget, where
 * error_budget = 1 - slo_target. Fire when burn ≥ threshold.
 */
class SloBurnRateStrategy implements AlertStrategy
{
    public function __construct(private readonly float $threshold = 2.0) {}

    public function key(): string
    {
        return 'slo_burn_rate';
    }

    public function shouldAlert(array $metrics): bool
    {
        $errorRate = (float) ($metrics['error_rate'] ?? 0.0);
        $sloTarget = (float) ($metrics['slo_target'] ?? 0.99);
        $budget = max(1 - $sloTarget, 1e-9);

        return ($errorRate / $budget) >= $this->threshold;
    }
}
