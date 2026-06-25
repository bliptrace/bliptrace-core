<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Incident;

/** Resolve an incident and compute MTTR in seconds (spec §7, P2). */
class ResolveIncident
{
    public function __invoke(Incident $incident): Incident
    {
        $resolvedAt = now();
        // Carbon 3 returns a float from diffInSeconds; mttr_seconds is a bigint.
        $mttr = $incident->opened_at
            ? (int) $resolvedAt->diffInSeconds($incident->opened_at, absolute: true)
            : null;

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
            'mttr_seconds' => $mttr,
        ]);

        return $incident;
    }
}
