<?php

declare(strict_types=1);

namespace G8Scope\Core\Alerting\Strategies;

use G8Scope\Core\Contracts\AlertStrategy;

/**
 * Dynamic-baseline anomaly detection (spec §7, P3). Fire when the current value is
 * z-score standard deviations above a rolling baseline mean.
 */
class AnomalyStrategy implements AlertStrategy
{
    public function __construct(private readonly float $z = 3.0) {}

    public function key(): string
    {
        return 'anomaly';
    }

    public function shouldAlert(array $metrics): bool
    {
        $mean = (float) ($metrics['baseline_mean'] ?? 0.0);
        $stddev = (float) ($metrics['baseline_stddev'] ?? 0.0);
        $current = (float) ($metrics['current'] ?? 0.0);

        if ($stddev <= 0.0) {
            return $current > $mean; // no variance baseline: any rise is anomalous
        }

        return (($current - $mean) / $stddev) >= $this->z;
    }
}
