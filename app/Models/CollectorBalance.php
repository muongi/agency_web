<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorBalance extends Model
{
    protected $fillable = [
        'agent_id',
        'uncollected_amount',
        'last_pay_in_at',
        'is_blocked',
        'blocked_at',
        'released_by',
        'released_at',
        'release_note',
    ];

    protected $casts = [
        'uncollected_amount' => 'decimal:2',
        'last_pay_in_at' => 'datetime',
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
