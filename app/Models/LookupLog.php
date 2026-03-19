<?php

namespace App\Models;

use App\Enums\LookupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookupLog extends Model
{
    protected $fillable = [
        'api_key_id',
        'method',
        'endpoint',
        'target',
        'type',
        'status_code',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'type' => LookupType::class,
            'status_code' => 'integer',
            'response_time_ms' => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
