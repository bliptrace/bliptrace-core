<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A deploy marker: a release shipped to an environment at a point in time. */
class Deploy extends Model
{
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['deployed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
