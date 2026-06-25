<?php

declare(strict_types=1);

namespace G8Scope\Core\Concerns;

use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;

/**
 * Dual ID convention (spec §17): a bigint auto-increment `id` for internal
 * relations + a UUID `uuid` for public-facing identifiers (route-bound on uuid).
 *
 * Composes cleaniquecoders/traitify's InteractsWithUuid for the uuid behaviour
 * (autofill on create, getRouteKeyName, scopeUuid) — the bigint `id` is Eloquent's
 * default key. Kept as a named trait so models read as "dual key" per the spec.
 */
trait HasDualKey
{
    use InteractsWithUuid;
}
