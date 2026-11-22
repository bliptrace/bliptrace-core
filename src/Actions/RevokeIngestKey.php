<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\IngestKey;

/** Revoke an ingest key. Idempotent: an already-revoked key keeps its original time. */
class RevokeIngestKey
{
    public function __invoke(IngestKey $key): IngestKey
    {
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return $key;
    }
}
