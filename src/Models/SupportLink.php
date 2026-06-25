<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use G8scope\Core\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLink extends Model
{
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SupportStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
