<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ApiKeyUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key_id',
        'date',
        'request_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'request_count' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('date', $date->toDateString());
    }
}
