<?php

declare(strict_types=1);

namespace G8scope\Core\Actions;

use G8scope\Core\Models\Incident;
use G8scope\Core\Models\Issue;

/** Open an incident for an issue (spec §7, P2). Idempotent per open issue. */
class OpenIncident
{
    public function __invoke(Issue $issue): Incident
    {
        return Incident::firstOrCreate(
            ['project_id' => $issue->project_id, 'issue_id' => $issue->id, 'status' => 'open'],
            ['opened_at' => now()],
        );
    }
}
