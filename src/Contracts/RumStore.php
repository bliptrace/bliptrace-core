<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Real-User-Monitoring store (spec §8, RUM — P3). Driver-based: Postgres (rows from
 * the Go ingest API, fed by the browser SDK) via the Eloquent driver in prod and tests.
 * Records Web-Vitals beacons and answers per-metric averages + ratings.
 */
interface RumStore
{
    /** @param array<string,mixed> $beacon project_id,metric,value,occurred_at */
    public function record(array $beacon): void;

    /**
     * Average + rating per Web Vital over a window.
     *
     * @param  list<int>  $projectIds
     * @return array<string,array{avg:float,rating:string,count:int}>
     */
    public function vitals(array $projectIds, int $sinceHours = 24): array;
}
