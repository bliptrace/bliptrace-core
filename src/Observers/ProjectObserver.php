<?php

declare(strict_types=1);

namespace G8scope\Core\Observers;

use G8scope\Core\Models\Endpoint;
use G8scope\Core\Models\IngestKey;
use G8scope\Core\Models\Project;
use G8scope\Core\Models\ProjectBinding;
use Illuminate\Support\Str;

/**
 * Spec §18: Project created → provision the default (shared) endpoint binding and
 * an ingest key. The raw key is generated once; only its hash is stored.
 */
class ProjectObserver
{
    public function created(Project $project): void
    {
        $plain = Str::random(40);
        IngestKey::create([
            'project_id' => $project->id,
            'name' => 'default',
            'key_hash' => hash('sha256', $plain),
            'last_four' => substr($plain, -4),
        ]);

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
