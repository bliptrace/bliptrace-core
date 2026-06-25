<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * A pluggable alerting strategy (spec §7, P3): given a metrics snapshot, decide
 * whether to fire. Examples: SLO burn-rate, anomaly (dynamic baseline).
 */
interface AlertStrategy
{
    public function key(): string;

    /** @param array<string,mixed> $metrics */
    public function shouldAlert(array $metrics): bool;
}
