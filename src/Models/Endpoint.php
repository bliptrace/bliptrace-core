<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use G8scope\Core\Enums\EndpointType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Endpoint extends Model
{
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => EndpointType::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
