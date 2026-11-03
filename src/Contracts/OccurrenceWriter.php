<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Write side for occurrences (Postgres). In production the Go ingest API writes;
 * core exposes this so the app/tests can record occurrences through the same store.
 * The table itself is owned by core's migration.
 */
interface OccurrenceWriter
{
    /** @param array<string,mixed> $occurrence */
    public function record(array $occurrence): void;
}
