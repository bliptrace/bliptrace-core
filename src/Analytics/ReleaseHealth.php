<?php

declare(strict_types=1);

namespace G8Scope\Core\Analytics;

use G8Scope\Core\Models\Issue;
use G8Scope\Core\Stores\PostgresOccurrenceStore;

/**
 * Compare error health across two releases (spec §8, P3): new issues introduced and
 * the change in occurrence volume. Both from Postgres (issues + occurrences).
 */
class ReleaseHealth
{
    public function __construct(private readonly PostgresOccurrenceStore $occurrences) {}

    /** @return array<string,mixed> */
    public function compare(int $projectId, string $before, string $after): array
    {
        $beforeFingerprints = Issue::where('project_id', $projectId)
            ->where('release', $before)->pluck('fingerprint')->all();

        $newIssues = Issue::where('project_id', $projectId)
            ->where('release', $after)
            ->whereNotIn('fingerprint', $beforeFingerprints ?: ['__none__'])
            ->count();

        $beforeOcc = $this->occurrences->countForRelease($projectId, $before);
        $afterOcc = $this->occurrences->countForRelease($projectId, $after);

        return [
            'before' => ['release' => $before, 'occurrences' => $beforeOcc],
            'after' => ['release' => $after, 'occurrences' => $afterOcc],
            'new_issues' => $newIssues,
            'occurrence_delta' => $afterOcc - $beforeOcc,
            'regressed' => $afterOcc > $beforeOcc || $newIssues > 0,
        ];
    }
}
