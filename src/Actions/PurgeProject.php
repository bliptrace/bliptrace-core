<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a project and every row that references it. Archive first
 * ({@see ArchiveProject}) and purge later: the Go ingest caches key lookups, so
 * a hard delete straight away lets an in-flight flush hit the issues→projects FK
 * and drop the whole multi-tenant batch.
 *
 * The append-only tables below carry project_id without a foreign key, so they
 * are purged here in chunks; everything with an FK goes with the project row.
 */
class PurgeProject
{
    /** Orphan-prone table => column that identifies one row within a project. */
    public const TABLES = [
        'occurrences' => 'event_id',
        'web_vitals' => 'id',
        'transactions' => 'id',
        'metric_points' => 'id',
    ];

    /** @param  array<string, string>  $extraTables  host-app tables (table => row key) without an FK to projects */
    public function __invoke(Project $project, array $extraTables = [], int $chunk = 1000): void
    {
        $projectId = $project->getKey();

        foreach ([...self::TABLES, ...$extraTables] as $table => $key) {
            // Select keys then delete by (project_id, key): a LIMIT-ed DELETE
            // compiles to a ctid subquery on Postgres, which is not unique
            // across occurrence partitions.
            do {
                $keys = DB::table($table)->where('project_id', $projectId)->limit($chunk)->pluck($key);

                if ($keys->isNotEmpty()) {
                    DB::table($table)->where('project_id', $projectId)->whereIn($key, $keys)->delete();
                }
            } while ($keys->count() === $chunk);
        }

        $project->forceDelete();
    }
}
