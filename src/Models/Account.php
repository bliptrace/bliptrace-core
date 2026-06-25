<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use HasDualKey;

    protected $guarded = [];

    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class);
    }
}
