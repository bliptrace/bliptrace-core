<?php

declare(strict_types=1);

namespace G8Scope\Core\Rum;

use G8Scope\Core\Contracts\RumStore;
use G8Scope\Core\Models\WebVital;

/**
 * Eloquent/Postgres RumStore driver (spec §8, §18). The production driver is
 * ClickHouse (fed by the Go ingest API + browser SDK). Ratings use the standard
 * Web-Vitals good/poor thresholds.
 */
class EloquentRumStore implements RumStore
{
    /** [good ≤, poor >] thresholds per metric (ms, except CLS which is unitless). */
    private const THRESHOLDS = [
        'lcp' => [2500, 4000],
        'inp' => [200, 500],
        'cls' => [0.1, 0.25],
        'fcp' => [1800, 3000],
        'ttfb' => [800, 1800],
    ];

    public function record(array $beacon): void
    {
        WebVital::create([
            'project_id' => $beacon['project_id'],
            'metric' => strtolower((string) ($beacon['metric'] ?? '')),
            'value' => (float) ($beacon['value'] ?? 0),
            'occurred_at' => $beacon['occurred_at'] ?? now(),
        ]);
    }

    public function vitals(array $projectIds, int $sinceHours = 24): array
    {
        $rows = WebVital::whereIn('project_id', $projectIds)
            ->where('occurred_at', '>=', now()->subHours($sinceHours))
            ->selectRaw('metric, avg(value) as average, count(*) as samples')
            ->groupBy('metric')
            ->get();

        $vitals = [];
        foreach ($rows as $row) {
            $avg = round((float) $row->average, 3);
            $vitals[$row->metric] = [
                'avg' => $avg,
                'rating' => $this->rating($row->metric, $avg),
                'count' => (int) $row->samples,
            ];
        }

        return $vitals;
    }

    private function rating(string $metric, float $value): string
    {
        $thresholds = self::THRESHOLDS[$metric] ?? null;
        if ($thresholds === null) {
            return 'unknown';
        }

        [$good, $poor] = $thresholds;

        return match (true) {
            $value <= $good => 'good',
            $value <= $poor => 'needs-improvement',
            default => 'poor',
        };
    }
}
