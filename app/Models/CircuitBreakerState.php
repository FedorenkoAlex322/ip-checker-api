<?php

namespace App\Models;

use App\Enums\CircuitState;
use Illuminate\Database\Eloquent\Model;

class CircuitBreakerState extends Model
{
    protected $fillable = [
        'service_name',
        'state',
        'failure_count',
        'success_count',
        'last_failure_at',
        'last_success_at',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => CircuitState::class,
            'failure_count' => 'integer',
            'success_count' => 'integer',
            'last_failure_at' => 'datetime',
            'last_success_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }
}
