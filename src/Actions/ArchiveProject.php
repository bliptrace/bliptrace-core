<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Archive (soft-delete) a project and revoke every active ingest key, so the
 * ingest API stops accepting its events at once. Issues and occurrences stay
 * until retention expires.
 */
class ArchiveProject
{
    public function __invoke(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->ingestKeys()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $project->delete();
        });
    }
}
