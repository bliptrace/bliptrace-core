<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Project extends Model implements Auditable
{
    use AuditableTrait;
    use HasDualKey;

    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function ingestKeys(): HasMany
    {
        return $this->hasMany(IngestKey::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function binding(): HasOne
    {
        return $this->hasOne(ProjectBinding::class);
    }

    public function supportLinks(): HasMany
    {
        return $this->hasMany(SupportLink::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }
}
