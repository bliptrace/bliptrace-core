<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Custom business-metrics store (spec §8, #34). Driver-based: Postgres (rows from
 * the Go ingest API) via the Eloquent driver in production and tests. Records named
 * numeric data points and answers aggregate series.
 */
interface MetricStore
{
    /** @param array<string,mixed> $point project_id,name,value,recorded_at */
    public function record(array $point): void;

    /**
     * Distinct metric names recorded for the given projects.
     *
     * @param  list<int>  $projectIds
     * @return list<string>
     */
    public function names(array $projectIds, int $sinceHours = 168): array;

    /**
     * Daily aggregate (sum + count + avg) for a metric over a window.
     *
     * @param  list<int>  $projectIds
     * @return list<array{day:string,sum:float,count:int,avg:float}>
     */
    public function series(array $projectIds, string $name, int $sinceHours = 168): array;
}
