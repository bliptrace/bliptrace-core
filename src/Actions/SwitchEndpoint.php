<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Contracts\EntitlementGate;
use G8Scope\Core\Enums\EndpointType;
use G8Scope\Core\Exceptions\EntitlementDeniedException;
use G8Scope\Core\Models\Endpoint;
use G8Scope\Core\Models\IngestKey;
use G8Scope\Core\Models\Project;
use Illuminate\Support\Str;

/**
 * Switch a project's api|mcp endpoint to a dedicated instance (spec §13.3): gate →
 * bind dedicated endpoint → re-issue ingest key. Requires the account entitlement.
 */
class SwitchEndpoint
{
    public function __construct(private readonly EntitlementGate $gate) {}

    public function __invoke(Project $project, string $kind, string $url): Endpoint
    {
        $capability = $kind === 'mcp' ? 'custom_mcp' : 'custom_api';
        if (! $this->gate->allows($project->account_id, $capability)) {
            throw new EntitlementDeniedException("Account is not entitled to {$capability}.");
        }

        $endpoint = Endpoint::create([
            'account_id' => $project->account_id,
            'kind' => $kind,
            'type' => EndpointType::Dedicated->value,
            'url' => $url,
        ]);

        $binding = $project->binding()->firstOrCreate([]);
        $binding->update([($kind === 'mcp' ? 'mcp_endpoint_id' : 'api_endpoint_id') => $endpoint->id]);

        // Re-issue the ingest key when re-pointing the project.
        $plain = Str::random(40);
        IngestKey::create([
            'project_id' => $project->id,
            'name' => "dedicated-{$kind}",
            'key_hash' => hash('sha256', $plain),
            'last_four' => substr($plain, -4),
        ]);

        return $endpoint;
    }
}
