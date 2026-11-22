<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\IngestKey;
use G8Scope\Core\Models\Project;
use Illuminate\Support\Str;

/**
 * Issue a revealable ingest key. Only the hash is stored; the plain key is
 * returned to the caller exactly once and can never be read back.
 */
class IssueIngestKey
{
    public const PREFIX = 'g8s_';

    /** @return array{0: IngestKey, 1: string} the key row and the plain key */
    public function __invoke(Project $project, string $name): array
    {
        $plain = self::PREFIX.Str::random(40);

        $key = $project->ingestKeys()->create([
            'name' => $name,
            'key_hash' => hash('sha256', $plain),
            'last_four' => substr($plain, -4),
        ]);

        return [$key, $plain];
    }
}
