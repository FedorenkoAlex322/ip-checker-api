<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LookupType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookupResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'api_key_id',
        'target',
        'type',
        'provider',
        'result_data',
        'lookup_time_ms',
        'cached',
    ];

    protected function casts(): array
    {
        return [
            'type' => LookupType::class,
            'result_data' => 'array',
            'cached' => 'boolean',
            'lookup_time_ms' => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Route model binding
    // -------------------------------------------------------------------------

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
