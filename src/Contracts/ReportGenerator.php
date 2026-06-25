<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

use G8Scope\Core\Enums\ReportType;

/**
 * Generates a report snapshot from CH + PG (spec §16). pdf / csv drivers.
 */
interface ReportGenerator
{
    /**
     * @param  array<string,mixed>  $options
     * @return string Path or URL of the generated artifact.
     */
    public function generate(int $projectId, ReportType $type, array $options = []): string;
}
