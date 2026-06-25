<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An APM transaction timing (spec §8, #35). */
class Transaction extends Model
{
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'status_code' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
