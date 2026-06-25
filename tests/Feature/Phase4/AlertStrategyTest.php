<?php

declare(strict_types=1);

use G8Scope\Core\Alerting\Strategies\AnomalyStrategy;
use G8Scope\Core\Alerting\Strategies\SloBurnRateStrategy;

it('fires SLO burn-rate above threshold', function (): void {
    $s = new SloBurnRateStrategy(threshold: 2.0);
    // budget = 1 - 0.99 = 0.01; rate 0.05 → burn 5 ≥ 2
    expect($s->shouldAlert(['error_rate' => 0.05, 'slo_target' => 0.99]))->toBeTrue()
        ->and($s->shouldAlert(['error_rate' => 0.005, 'slo_target' => 0.99]))->toBeFalse()
        ->and($s->key())->toBe('slo_burn_rate');
});

it('fires anomaly beyond z-score', function (): void {
    $s = new AnomalyStrategy(z: 3.0);
    expect($s->shouldAlert(['baseline_mean' => 10, 'baseline_stddev' => 2, 'current' => 20]))->toBeTrue()
        ->and($s->shouldAlert(['baseline_mean' => 10, 'baseline_stddev' => 2, 'current' => 12]))->toBeFalse();
});
