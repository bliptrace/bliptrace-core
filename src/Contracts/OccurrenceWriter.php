<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Write side for occurrences (ClickHouse). In production the Go ingest API writes;
 * core exposes this so the app/tests can record occurrences through the same store.
 */
interface OccurrenceWriter
{
    /** @param array<string,mixed> $occurrence */
    public function record(array $occurrence): void;

    /** Ensure the occurrences table exists (idempotent). */
    public function migrate(): void;
}
