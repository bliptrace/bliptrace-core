<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use G8scope\Core\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
