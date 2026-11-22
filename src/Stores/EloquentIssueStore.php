<?php

declare(strict_types=1);

namespace G8Scope\Core\Stores;

use G8Scope\Core\Contracts\IssueStore;
use G8Scope\Core\Enums\IssueStatus;
use G8Scope\Core\Models\Issue;
use Illuminate\Support\Facades\DB;

/**
 * Issue persistence in Postgres (spec §8). find-or-create by (project_id,
 * fingerprint), bump counters, and reopen on regression (resolved → reoccurs).
 *
 * The returned Issue carries two transient flags for the caller:
 *  - wasRecentlyCreated (Eloquent) → new issue
 *  - justRegressed (custom)        → a resolved issue reoccurred
 */
class EloquentIssueStore implements IssueStore
{
    public function upsert(int $projectId, string $fingerprint, array $attributes): Issue
    {
        return DB::transaction(function () use ($projectId, $fingerprint, $attributes): Issue {
            $when = $attributes['occurred_at'] ?? now();

            $issue = Issue::query()
                ->where('project_id', $projectId)
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($issue === null) {
                return Issue::create([
                    'project_id' => $projectId,
                    'fingerprint' => $fingerprint,
                    'type' => $attributes['type'] ?? 'Error',
                    'message' => $attributes['message'] ?? null,
                    'culprit' => $attributes['culprit'] ?? null,
                    'level' => $attributes['level'] ?? 'error',
                    'status' => IssueStatus::Open->value,
                    'environment' => $attributes['environment'] ?? null,
                    'release' => $attributes['release'] ?? null,
                    'first_seen_at' => $when,
                    'last_seen_at' => $when,
                    'occurrences_count' => 1,
                ]);
            }

            $issue->occurrences_count += 1;
            $issue->last_seen_at = $when;

            if ($issue->status === IssueStatus::Resolved) {
                $issue->status = IssueStatus::Open;
                $issue->resolved_at = null;
                $issue->regressed_at = $when;
                $issue->justRegressed = true;
            }

            $issue->save();

            return $issue;
        });
    }

    public function setStatus(string $issueUuid, IssueStatus $status): void
    {
        $issue = Issue::where('uuid', $issueUuid)->firstOrFail();
        $issue->status = $status;
        $issue->resolved_at = $status === IssueStatus::Resolved ? now() : null;
        $issue->save();
    }

    public function assign(string $issueUuid, ?int $userId): void
    {
        Issue::where('uuid', $issueUuid)->firstOrFail()
            ->update(['assignee_user_id' => $userId]);
    }
}
