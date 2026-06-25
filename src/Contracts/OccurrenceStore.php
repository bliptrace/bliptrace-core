<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Occurrence access in ClickHouse (spec §8). Append-only; the web app reads,
 * the Go ingest API writes. One issue groups thousands of occurrences.
 */
interface OccurrenceStore
{
    /** @return iterable<int,array<string,mixed>> Latest occurrences for an issue. */
    public function forIssue(string $issueUuid, int $limit = 50): iterable;

    /** @return array<int,array{bucket:string,count:int}> Counts-over-time sparkline. */
    public function sparkline(string $issueUuid, string $interval = '1h'): array;
}
