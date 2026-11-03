<?php

declare(strict_types=1);

namespace G8Scope\Core\Stores;

use G8Scope\Core\Contracts\OccurrenceStore;
use G8Scope\Core\Contracts\OccurrenceWriter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Occurrences in Postgres (`occurrences`, range-partitioned monthly on occurred_at).
 * Production writes come from the Go api; core owns the schema + read queries and
 * can write for the app/tests.
 *
 * Idempotency is per (project_id, event_id, occurred_at), NOT event_id alone: the PK
 * must include the partition key (occurred_at), so a retry that re-sends the same
 * event_id with a different occurred_at lands as a second row. Clients must replay
 * the original occurred_at.
 */
class PostgresOccurrenceStore implements OccurrenceStore, OccurrenceWriter
{
    private const TABLE = 'occurrences';

    public function record(array $occurrence): void
    {
        $payload = $occurrence['payload'] ?? [];

        $this->table()->insertOrIgnore([
            'event_id' => (string) ($occurrence['event_id'] ?? '') ?: (string) Str::uuid(),
            'project_id' => (int) ($occurrence['project_id'] ?? 0),
            'issue_uuid' => (string) ($occurrence['issue_uuid'] ?? ''),
            'fingerprint' => (string) ($occurrence['fingerprint'] ?? ''),
            'level' => (string) ($occurrence['level'] ?? 'error'),
            'environment' => (string) ($occurrence['environment'] ?? ''),
            'release' => (string) ($occurrence['release'] ?? ''),
            'message' => (string) ($occurrence['message'] ?? ''),
            'occurred_at' => $occurrence['occurred_at'] ?? gmdate('Y-m-d H:i:s.v\Z'),
            'payload' => is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function forIssue(string $issueUuid, int $limit = 50): iterable
    {
        return $this->issueQuery($issueUuid)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public function sparkline(string $issueUuid, string $interval = '1h'): array
    {
        return $this->buckets($this->issueQuery($issueUuid), $interval);
    }

    /**
     * Events over time across one or more projects (for analytics), last $hours hours.
     *
     * @param  array<int,int>  $projectIds
     * @return array<int,array{bucket:string,count:int}>
     */
    public function eventsOverTime(array $projectIds, string $interval = '1h', int $hours = 48): array
    {
        if ($projectIds === []) {
            return [];
        }

        return $this->buckets(
            $this->table()
                ->whereIn('project_id', array_map('intval', $projectIds))
                ->where('occurred_at', '>=', now()->subHours($hours)),
            $interval,
        );
    }

    /** Total occurrences for a project release — used by ReleaseHealth. */
    public function countForRelease(int $projectId, string $release): int
    {
        return $this->table()->where('project_id', $projectId)->where('release', $release)->count();
    }

    private function table(): Builder
    {
        return DB::table(self::TABLE);
    }

    /** Scope by project too, so the (project_id, issue_uuid, occurred_at) index is used. */
    private function issueQuery(string $issueUuid): Builder
    {
        return $this->table()
            ->where('project_id', fn (Builder $q) => $q->select('project_id')->from('issues')->where('uuid', $issueUuid))
            ->where('issue_uuid', $issueUuid);
    }

    /** @return array<int,array{bucket:string,count:int}> */
    private function buckets(Builder $query, string $interval): array
    {
        $bucket = DB::getDriverName() === 'pgsql'
            ? "to_char(date_trunc('".match ($interval) {
                '1m' => 'minute', '1d' => 'day', default => 'hour',
            }."', occurred_at AT TIME ZONE 'UTC'), 'YYYY-MM-DD HH24:MI:SS')"
            : "strftime('".match ($interval) {
                '1m' => '%Y-%m-%d %H:%M:00', '1d' => '%Y-%m-%d 00:00:00', default => '%Y-%m-%d %H:00:00',
            }."', occurred_at)";

        return $query->selectRaw($bucket.' AS bucket, count(*) AS count')
            ->groupBy('bucket')->orderBy('bucket')->get()
            ->map(static fn ($r) => ['bucket' => (string) $r->bucket, 'count' => (int) $r->count])
            ->all();
    }
}
