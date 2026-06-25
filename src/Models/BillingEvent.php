<?php

declare(strict_types=1);

namespace G8Scope\Core\Models;

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A processed billing event (spec §12–13, #82). The (provider, event_id) pair is
 * unique — the webhook handler relies on that to stay idempotent under redelivery.
 */
class BillingEvent extends Model implements Auditable
{
    use AuditableTrait;
    use HasDualKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
