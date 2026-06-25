<?php

declare(strict_types=1);

namespace G8scope\Core\Actions;

use G8scope\Core\Models\Incident;

/** Resolve an incident and compute MTTR in seconds (spec §7, P2). */
class ResolveIncident
{
    public function __invoke(Incident $incident): Incident
    {
        $resolvedAt = now();
        $mttr = $incident->opened_at
            ? $resolvedAt->diffInSeconds($incident->opened_at, absolute: true)
            : null;

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
            'mttr_seconds' => $mttr,
        ]);

        return $incident;
    }
}
