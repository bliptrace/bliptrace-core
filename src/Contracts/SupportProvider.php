<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Pluggable support backend (spec §15). g8desk is the first driver. Attaches a
 * scrubbed issue to a ticket and returns its reference (DESK-YYYY-NNNNN).
 */
interface SupportProvider
{
    /** @param array<string,mixed> $scrubbedContext */
    public function attach(string $issueUuid, array $scrubbedContext): string;
}
