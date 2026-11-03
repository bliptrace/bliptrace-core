<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use G8Scope\Core\Models\Entitlement;
use Illuminate\Support\Facades\DB;

/**
 * Occurrence retention + partition upkeep (run daily by g8scope:occurrences:maintain).
 * Creates the current + next two monthly partitions and drops partitions whose whole
 * range is older than the retention window. Postgres only; elsewhere it just deletes
 * expired rows.
 */
class MaintainOccurrencePartitions
{
    public const TABLE = 'occurrences';

    private const AHEAD_MONTHS = 2;

    /** @return array{created: list<string>, dropped: list<string>, cutoff: string} */
    public function __invoke(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $cutoff = $now->subDays($this->retentionDays());

        if (DB::getDriverName() !== 'pgsql') {
            DB::table(self::TABLE)->where('occurred_at', '<', $cutoff)->delete();

            return ['created' => [], 'dropped' => [], 'cutoff' => $cutoff->toIso8601String()];
        }

        return [
            'created' => $this->ensurePartitions($now),
            'dropped' => $this->dropExpired($cutoff),
            'cutoff' => $cutoff->toIso8601String(),
        ];
    }

    /**
     * Global retention: the configured default, raised to the longest entitlement so a
     * customer with a longer paid retention never loses data to a shared partition drop.
     */
    public function retentionDays(): int
    {
        return max(
            (int) config('g8scope.occurrences.retention_days', 14),
            (int) Entitlement::query()->max('retention_days'),
        );
    }

    /** @return list<string> partitions created */
    public function ensurePartitions(?CarbonInterface $now = null): array
    {
        $month = CarbonImmutable::instance($now ?? now())->utc()->startOfMonth();
        $created = [];

        for ($i = 0; $i <= self::AHEAD_MONTHS; $i++) {
            $from = $month->addMonths($i);
            $name = self::partitionName($from);

            if (DB::selectOne('SELECT to_regclass(?) AS t', [$name])->t !== null) {
                continue;
            }

            $this->createPartition($name, $from, $from->addMonth());
            $created[] = $name;
        }

        return $created;
    }

    /** @return list<string> partitions dropped */
    public function dropExpired(CarbonInterface $cutoff): array
    {
        $dropped = [];

        foreach ($this->partitions() as $name) {
            if (! preg_match('/^'.self::TABLE.'_(\d{4})_(\d{2})$/', $name, $m)) {
                continue;
            }

            $end = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, 'UTC')->addMonth();
            if ($end->lessThanOrEqualTo($cutoff)) {
                DB::statement('DROP TABLE '.$name);
                $dropped[] = $name;
            }
        }

        // Out-of-range rows (e.g. late backfill) sit in the default partition.
        DB::table(self::TABLE.'_default')->where('occurred_at', '<', $cutoff)->delete();

        return $dropped;
    }

    public static function partitionName(CarbonInterface $month): string
    {
        return self::TABLE.'_'.$month->format('Y_m');
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
            ->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);

        if (! $inRange()->exists()) {
            DB::statement($create);

            return;
        }

        // Postgres refuses a new partition while the default holds rows in its range:
        // detach default, create, move those rows across, re-attach — atomically.
        DB::transaction(function () use ($name, $create, $inRange): void {
            DB::statement('ALTER TABLE '.self::TABLE.' DETACH PARTITION '.self::TABLE.'_default');
            DB::statement($create);
            DB::statement('INSERT INTO '.$name.' '.$inRange()->toRawSql());
            $inRange()->delete();
            DB::statement('ALTER TABLE '.self::TABLE.' ATTACH PARTITION '.self::TABLE.'_default DEFAULT');
        });
    }
}
