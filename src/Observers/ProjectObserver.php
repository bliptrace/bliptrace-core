<?php

declare(strict_types=1);

namespace G8Scope\Core\Observers;

use G8Scope\Core\Actions\IssueIngestKey;
use G8Scope\Core\Models\Endpoint;
use G8Scope\Core\Models\Project;
use G8Scope\Core\Models\ProjectBinding;

/**
 * Spec §18: Project created → provision the default (shared) endpoint binding and
 * an ingest key. The raw key is generated once; only its hash is stored.
 */
class ProjectObserver
{
    public function created(Project $project): void
    {
        app(IssueIngestKey::class)($project, 'default');

        $api = Endpoint::firstOrCreate(
            ['account_id' => $project->account_id, 'kind' => 'api', 'type' => 'shared'],
            ['url' => config('g8scope.endpoints.api', 'https://api.g8suite.com')],
        );
        $mcp = Endpoint::firstOrCreate(
            ['account_id' => $project->account_id, 'kind' => 'mcp', 'type' => 'shared'],
            ['url' => config('g8scope.endpoints.mcp', 'https://mcp.g8suite.com')],
        );

        ProjectBinding::create([
            'project_id' => $project->id,
            'api_endpoint_id' => $api->id,
            'mcp_endpoint_id' => $mcp->id,
        ]);
    }
}
