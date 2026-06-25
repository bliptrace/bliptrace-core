<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A RUM Web-Vital beacon (spec §8, #36). */
class WebVital extends Model
{
    use HasDualKey;

    protected $table = 'web_vitals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
