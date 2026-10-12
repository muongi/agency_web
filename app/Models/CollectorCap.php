<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorCap extends Model
{
    protected $fillable = [
        'agent_id',
        'cap_amount',
        'warning_threshold_pct',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'cap_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
