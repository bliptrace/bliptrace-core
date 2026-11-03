<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use G8Scope\Core\Models\Entitlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Occurrence retention + partition upkeep (run daily by g8scope:occurrences:maintain).
 *  - Creates the current + next two monthly partitions (Postgres only).
 *  - Drops partitions whose whole range is older than the GLOBAL cutoff: the longest
 *    retention any account is actually granted, so a partition drop never takes data a
 *    customer paid to keep.
 *  - Deletes, per project in batches, rows older than THAT project's retention (its
 *    account's granted entitlement, else the config default), so a shared-tier project
 *    does not inherit a 365-day customer's retention through a shared partition.
 *
 * Every timestamptz comparison binds an ISO-8601 string with an explicit offset, so the
 * result does not depend on the session TimeZone.
 */
class MaintainOccurrencePartitions
{
    public const TABLE = 'occurrences';

    public const BATCH = 5000;

    private const AHEAD_MONTHS = 2;

    /** @return array{created: list<string>, dropped: list<string>, deleted: int, cutoff: string, dry_run: bool} */
    public function __invoke(?CarbonInterface $now = null, bool $dryRun = false): array
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $cutoff = $now->subDays($this->retentionDays());
        $pgsql = DB::getDriverName() === 'pgsql';

        // Per-project deletes first, so their counts include rows a later drop would also take.
        $deleted = $this->deleteExpiredPerProject($now, $dryRun);

        return [
            'created' => $pgsql ? $this->ensurePartitions($now, $dryRun) : [],
            'dropped' => $pgsql ? $this->dropExpired($cutoff, $dryRun) : [],
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Global retention (the partition-drop upper bound): the configured default, raised
     * to the longest GRANTED entitlement. An ungranted entitlement extends nothing.
     */
    public function retentionDays(): int
    {
        return max(
            $this->defaultRetentionDays(),
            (int) Entitlement::query()->whereNotNull('granted_at')->max('retention_days'),
        );
    }

    /** @return list<string> partitions created (or that would be, on a dry run) */
    public function ensurePartitions(?CarbonInterface $now = null, bool $dryRun = false): array
    {
        $month = CarbonImmutable::instance($now ?? now())->utc()->startOfMonth();
        $created = [];

        for ($i = 0; $i <= self::AHEAD_MONTHS; $i++) {
            $from = $month->addMonths($i);
            $name = self::partitionName($from);

            if (DB::selectOne('SELECT to_regclass(?) AS t', [$name])->t !== null) {
                continue;
            }

            if (! $dryRun) {
                $this->createPartition($name, $from, $from->addMonth());
            }
            $created[] = $name;
        }

        return $created;
    }

    /** @return list<string> partitions dropped (or that would be, on a dry run) */
    public function dropExpired(CarbonInterface $cutoff, bool $dryRun = false): array
    {
        $cutoff = CarbonImmutable::instance($cutoff)->utc();
        $dropped = [];

        foreach ($this->partitions() as $name) {
            if (! preg_match('/^'.self::TABLE.'_(\d{4})_(\d{2})$/', $name, $m)) {
                continue;
            }

            $end = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, 'UTC')->addMonth();
            if ($end->lessThanOrEqualTo($cutoff)) {
                Log::info('g8scope occurrences: dropping expired partition', [
                    'partition' => $name, 'rows' => DB::table($name)->count(), 'dry_run' => $dryRun,
                ]);
                if (! $dryRun) {
                    DB::statement('DROP TABLE '.$name);
                }
                $dropped[] = $name;
            }
        }

        // Out-of-range rows (e.g. late backfill) sit in the default partition.
        if (! $dryRun) {
            DB::table(self::TABLE.'_default')->where('occurred_at', '<', $cutoff->toIso8601String())->delete();
        }

        return $dropped;
    }

    /** Rows deleted (or that would be) because they are past their own project's retention. */
    public function deleteExpiredPerProject(?CarbonInterface $now = null, bool $dryRun = false): int
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $default = $this->defaultRetentionDays();
        $total = 0;

        $projects = DB::table('projects')
            ->leftJoin('entitlements', function ($join): void {
                $join->on('entitlements.account_id', '=', 'projects.account_id')->whereNotNull('entitlements.granted_at');
            })
            ->orderBy('projects.id')
            ->get(['projects.id', 'entitlements.retention_days']);

        foreach ($projects as $project) {
            $days = $project->retention_days !== null ? (int) $project->retention_days : $default;
            $cutoff = $now->subDays($days)->toIso8601String();
            $expired = fn () => DB::table(self::TABLE)->where('project_id', $project->id)->where('occurred_at', '<', $cutoff);

            if ($dryRun) {
                $total += $expired()->count();

                continue;
            }

            $deleted = 0;
            do {
                // Batched by primary key: ctid is not unique across partitions.
                $batch = DB::table(self::TABLE)
                    ->whereIn(DB::raw('(project_id, occurred_at, event_id)'), $expired()->select('project_id', 'occurred_at', 'event_id')->limit(self::BATCH))
                    ->delete();
                $deleted += $batch;
            } while ($batch === self::BATCH);

            if ($deleted > 0) {
                Log::info('g8scope occurrences: deleted rows past project retention', [
                    'project_id' => $project->id, 'rows' => $deleted, 'retention_days' => $days,
                ]);
            }
            $total += $deleted;
        }

        return $total;
    }

    public static function partitionName(CarbonInterface $month): string
    {
        return self::TABLE.'_'.$month->format('Y_m');
    }

    private function defaultRetentionDays(): int
    {
        return (int) config('g8scope.occurrences.retention_days', 14);
    }

    /** @return list<string> */
    private function partitions(): array
    {
        return array_map(static fn ($r) => (string) $r->name, DB::select(
            'SELECT c.relname AS name FROM pg_inherits i
             JOIN pg_class c ON c.oid = i.inhrelid
             WHERE i.inhparent = ?::regclass ORDER BY c.relname',
            [self::TABLE],
        ));
    }

    private function createPartition(string $name, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $bounds = sprintf("FROM ('%s') TO ('%s')", $from->format('Y-m-d H:i:sP'), $to->format('Y-m-d H:i:sP'));
        $create = 'CREATE TABLE '.$name.' PARTITION OF '.self::TABLE.' FOR VALUES '.$bounds;
        $inRange = fn () => DB::table(self::TABLE.'_default')
            ->where('occurred_at', '>=', $from->toIso8601String())
            ->where('occurred_at', '<', $to->toIso8601String());

        if (! $inRange()->exists()) {
            DB::statement($create);

            return;
        }

        // Postgres refuses a new partition while the default holds rows in its range:
        // detach default, create, move those rows across, re-attach — atomically.
        DB::transaction(function () use ($name, $create, $inRange): void {
            DB::statement("SET LOCAL TIME ZONE 'UTC'");
            DB::statement('ALTER TABLE '.self::TABLE.' DETACH PARTITION '.self::TABLE.'_default');
            DB::statement($create);
            DB::table($name)->insertUsing([], $inRange());
            $inRange()->delete();
            DB::statement('ALTER TABLE '.self::TABLE.' ATTACH PARTITION '.self::TABLE.'_default DEFAULT');
        });
    }
}
