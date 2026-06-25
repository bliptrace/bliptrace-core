<?php

declare(strict_types=1);

namespace G8scope\Core\Contracts;

use G8scope\Core\Enums\IssueStatus;

/**
 * Issue persistence in Postgres (spec §8). Dedup is an upsert
 * ON CONFLICT (project_id, fingerprint).
 */
interface IssueStore
{
    /**
     * Find-or-create the issue for a fingerprint and bump its counters.
     *
     * @param array<string,mixed> $attributes
     * @return object The issue (with public uuid + internal id).
     */
    public function upsert(int $projectId, string $fingerprint, array $attributes): object;

    public function setStatus(string $issueUuid, IssueStatus $status): void;

    public function assign(string $issueUuid, ?int $userId): void;
}
