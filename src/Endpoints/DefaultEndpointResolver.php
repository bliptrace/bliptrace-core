<?php

declare(strict_types=1);

namespace G8scope\Core\Endpoints;

use G8scope\Core\Contracts\EndpointResolver;
use G8scope\Core\Enums\EndpointType;
use G8scope\Core\Models\Project;

/**
 * Resolves the endpoint URL a project uses for a given kind (api|mcp): the bound
 * dedicated endpoint when present, otherwise the shared default (spec §13).
 */
class DefaultEndpointResolver implements EndpointResolver
{
    public function resolve(int $projectId, string $kind): string
    {
        $project = Project::with('binding.apiEndpoint', 'binding.mcpEndpoint')->findOrFail($projectId);
        $binding = $project->binding;

        $endpoint = $kind === 'mcp' ? $binding?->mcpEndpoint : $binding?->apiEndpoint;

        if ($endpoint && $endpoint->type === EndpointType::Dedicated) {
            return $endpoint->url;
        }

        return (string) config("g8scope.endpoints.{$kind}");
    }
}
