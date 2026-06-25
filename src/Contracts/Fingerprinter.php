<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Grouping quality IS the product (spec §11). Produces a stable fingerprint from
 * an envelope: hash of project_id + exception.type + normalised top in-app frames
 * + culprit. Honours a client fingerprint_hint when present.
 */
interface Fingerprinter
{
    /** @param array<string,mixed> $envelope */
    public function fingerprint(int $projectId, array $envelope): string;
}
