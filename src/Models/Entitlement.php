<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $uuid
 * @property int $account_id
 * @property bool $custom_api
 * @property bool $custom_mcp
 * @property Carbon|null $granted_at
 * @property int $seats
 * @property int|null $max_projects
 * @property int $retention_days
 * @property string|null $isolation_connection
 */
class Entitlement extends Model implements Auditable
{
    use AuditableTrait;
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'custom_api' => 'boolean',
            'custom_mcp' => 'boolean',
            'granted_at' => 'datetime',
            'seats' => 'integer',
            'max_projects' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function unlocked(): bool
    {
        return $this->granted_at !== null;
    }
}
