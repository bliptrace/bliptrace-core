<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Deploy;
use G8Scope\Core\Models\Project;

/** Record a deploy marker so a regression can be tied to the release that caused it. */
class RecordDeploy
{
    /**
     * @param  array{release: string, environment?: ?string, commit_sha?: ?string, url?: ?string, deployed_at?: mixed}  $attributes
     */
    public function __invoke(Project $project, array $attributes, ?int $userId = null): Deploy
    {
        return $project->deploys()->create([
            'release' => $attributes['release'],
            'environment' => $attributes['environment'] ?? null,
            'commit_sha' => $attributes['commit_sha'] ?? null,
            'url' => $attributes['url'] ?? null,
            'deployed_at' => $attributes['deployed_at'] ?? now(),
            'created_by' => $userId,
        ]);
    }
}
