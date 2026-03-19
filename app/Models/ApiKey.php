<?php

namespace App\Models;

use App\Enums\ApiKeyTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $expires_at
 * @property-read Carbon|null $last_used_at
 */
class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key_hash',
        'name',
        'tier',
        'daily_limit',
        'monthly_limit',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => ApiKeyTier::class,
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'daily_limit' => 'integer',
            'monthly_limit' => 'integer',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function usages(): HasMany
    {
        return $this->hasMany(ApiKeyUsage::class);
    }

    public function lookupResults(): HasMany
    {
        return $this->hasMany(LookupResult::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LookupLog::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTier(Builder $query, ApiKeyTier $tier): Builder
    {
        return $query->where('tier', $tier->value);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }
}
