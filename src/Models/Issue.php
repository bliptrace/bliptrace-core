<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use G8scope\Core\Enums\IssueStatus;
use G8scope\Core\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Issue extends Model implements Auditable
{
    use AuditableTrait;
    use HasDualKey;

    protected $guarded = [];

    /** Only audit the fields that matter for triage history. */
    protected array $auditInclude = ['status', 'assignee_user_id', 'level'];

    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'status' => IssueStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrences_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supportLinks(): HasMany
    {
        return $this->hasMany(SupportLink::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
