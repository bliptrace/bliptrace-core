<?php

declare(strict_types=1);

namespace G8scope\Core\Contracts;

use G8scope\Core\Enums\EndpointType;

/**
 * Resolves which API/MCP endpoint a project uses: default (shared) ▸ custom
 * (dedicated), only when the entitlement grants it (spec §13, §18).
 */
interface EndpointResolver
{
    public function resolve(int $projectId, EndpointType $type): string;
}
