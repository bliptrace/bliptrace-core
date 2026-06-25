<?php

declare(strict_types=1);

namespace G8scope\Core\Models;

use G8scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBinding extends Model
{
    use HasDualKey;

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function apiEndpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'api_endpoint_id');
    }

    public function mcpEndpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class, 'mcp_endpoint_id');
    }
}
