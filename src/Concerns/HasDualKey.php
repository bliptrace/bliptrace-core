<?php

declare(strict_types=1);

namespace G8scope\Core\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Dual ID convention (spec §17): a bigint auto-increment `id` for internal
 * relations + a UUID `uuid` for public-facing identifiers. Route-binds on uuid.
 *
 * @mixin Model
 */
trait HasDualKey
{
    public static function bootHasDualKey(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
