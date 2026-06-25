<?php

declare(strict_types=1);

namespace G8Scope\Core\Apm;

use G8Scope\Core\Contracts\ApmStore;
use G8Scope\Core\Models\Transaction;

/**
 * Eloquent/Postgres ApmStore driver (spec §8, §18). Percentiles are computed in
 * PHP so the same driver works on SQLite (tests) and Postgres. The production
 * driver is ClickHouse (fed by the Go ingest API).
 */
class EloquentApmStore implements ApmStore
{
    /** Apdex target T in milliseconds (satisfied ≤ T, tolerating ≤ 4T). */
    private const APDEX_T = 500;

    public function record(array $transaction): void
    {
        Transaction::create([
            'project_id' => $transaction['project_id'],
            'name' => $transaction['name'] ?? 'unknown',
            'type' => $transaction['type'] ?? 'http',
            'duration_ms' => (int) ($transaction['duration_ms'] ?? 0),
            'status_code' => $transaction['status_code'] ?? null,
            'occurred_at' => $transaction['occurred_at'] ?? now(),
        ]);
    }

    public function summary(array $projectIds, int $sinceHours = 24): array
    {
        $durations = Transaction::whereIn('project_id', $projectIds)
            ->where('occurred_at', '>=', now()->subHours($sinceHours))
            ->orderBy('duration_ms')
            ->pluck('duration_ms')
            ->map(fn ($d) => (int) $d)
            ->all();

        $count = count($durations);
        if ($count === 0) {
            return ['count' => 0, 'p50' => 0.0, 'p95' => 0.0, 'p99' => 0.0, 'apdex' => 1.0];
        }

        $satisfied = 0;
        $tolerating = 0;
        foreach ($durations as $d) {
            if ($d <= self::APDEX_T) {
                $satisfied++;
            } elseif ($d <= self::APDEX_T * 4) {
                $tolerating++;
            }
        }

        return [
            'count' => $count,
            'p50' => $this->percentile($durations, 0.50),
            'p95' => $this->percentile($durations, 0.95),
            'p99' => $this->percentile($durations, 0.99),
            'apdex' => round(($satisfied + $tolerating / 2) / $count, 3),
        ];
    }

    public function slowest(array $projectIds, int $limit = 10, int $sinceHours = 24): array
    {
        return Transaction::whereIn('project_id', $projectIds)
            ->where('occurred_at', '>=', now()->subHours($sinceHours))
            ->orderByDesc('duration_ms')
            ->limit($limit)
            ->get(['name', 'type', 'duration_ms', 'status_code', 'occurred_at'])
            ->map(fn (Transaction $t) => $t->toArray())
            ->all();
    }

    /** @param list<int> $sorted ascending */
    private function percentile(array $sorted, float $q): float
    {
        $index = (int) ceil($q * count($sorted)) - 1;
        $index = max(0, min($index, count($sorted) - 1));

        return (float) $sorted[$index];
    }
}
