<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Per-runtime stack normaliser (spec §11). Selected by envelope.runtime.name.
 * Normalises wildly different stack formats (PHP, minified JS, Python, Go) into a
 * comparable shape so the Fingerprinter groups consistently across runtimes.
 */
interface Normalizer
{
    /** The runtime this normaliser handles, e.g. "php", "javascript". */
    public function runtime(): string;

    /**
     * @param  array<int,array<string,mixed>>  $frames
     * @return array<int,array<string,mixed>>
     */
    public function normalize(array $frames): array;
}
