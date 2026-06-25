<?php

declare(strict_types=1);

namespace G8Scope\Core\Contracts;

/**
 * Resolves the endpoint URL a project uses for a given kind (api|mcp): the bound
 * dedicated endpoint when entitled, otherwise the shared default (spec §13, §18).
 */
interface EndpointResolver
{
    /** @param 'api'|'mcp' $kind */
    public function resolve(int $projectId, string $kind): string;
}
