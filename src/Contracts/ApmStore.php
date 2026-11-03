<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Transaction-performance store (spec §8, APM — P3). Driver-based: the Go ingest
 * API writes Postgres; the Eloquent driver reads it in production and tests.
 * Records transaction timings and answers latency/Apdex summaries.
 */
interface ApmStore
{
    /** @param array<string,mixed> $transaction project_id,name,duration_ms,status_code,occurred_at */
    public function record(array $transaction): void;

    /**
     * Latency + Apdex summary over a window.
     *
     * @param  list<int>  $projectIds
     * @return array{count:int,p50:float,p95:float,p99:float,apdex:float}
     */
    public function summary(array $projectIds, int $sinceHours = 24): array;

    /**
     * The slowest transactions in the window.
     *
     * @param  list<int>  $projectIds
     * @return list<array<string,mixed>>
     */
    public function slowest(array $projectIds, int $limit = 10, int $sinceHours = 24): array;
}
