<?php

declare(strict_types=1);

namespace G8Scope\Core\Metrics;

use G8Scope\Core\Contracts\MetricStore;
use G8Scope\Core\Models\MetricPoint;

/**
 * Eloquent/Postgres MetricStore driver (spec §8, §18). The production driver is
 * ClickHouse (fed by the Go ingest API). Daily aggregation is grouped in SQL via
 * date() so it works on SQLite (tests) + Postgres.
 */
class EloquentMetricStore implements MetricStore
{
    public function record(array $point): void
    {
        MetricPoint::create([
            'project_id' => $point['project_id'],
            'name' => $point['name'] ?? 'unknown',
            'value' => (float) ($point['value'] ?? 0),
            'recorded_at' => $point['recorded_at'] ?? now(),
        ]);
    }

    public function names(array $projectIds, int $sinceHours = 168): array
    {
        return MetricPoint::whereIn('project_id', $projectIds)
            ->where('recorded_at', '>=', now()->subHours($sinceHours))
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function series(array $projectIds, string $name, int $sinceHours = 168): array
    {
        return MetricPoint::whereIn('project_id', $projectIds)
            ->where('name', $name)
            ->where('recorded_at', '>=', now()->subHours($sinceHours))
            ->selectRaw('date(recorded_at) as day, sum(value) as total, count(*) as points')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => (string) $r->day,
                'sum' => (float) $r->total,
                'count' => (int) $r->points,
                'avg' => $r->points > 0 ? round((float) $r->total / (int) $r->points, 3) : 0.0,
            ])
            ->all();
    }
}
