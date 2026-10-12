<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SunuPremiumCollection extends Model
{
    public const KIND_POLICY_COST = 'policy_cost';
    public const KIND_FIRST_PREMIUM = 'first_premium';
    public const KIND_RECURRING = 'recurring_premium';

    public const STATUS_VALID = 'valid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_unit_id',
        'policy_id',
        'proposal_id',
        'agent_id',
        'terminal_id',
        'transaction_id',
        'amount',
        'collection_kind',
        'status',
        'cancelled_at',
        'cancelled_by',
        'correction_request_id',
        'reference_period',
        'payment_frequency',
        'payment_instrument',
        'reference',
        'latitude',
        'longitude',
        'collected_at',
        'receipt_number',
        'metadata',
        'sunulife_pushed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'collected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'sunulife_pushed_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SunuPolicy::class, 'policy_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(SunuProposal::class, 'proposal_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Premiums that count (not cancelled after a correction request). */
    public function scopeValid($query)
    {
        return $query->where($query->getModel()->getTable().'.status', '!=', self::STATUS_CANCELLED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function correctionRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SunuCorrectionRequest::class, 'target_id')
            ->where('target_type', SunuCorrectionRequest::TARGET_PREMIUM)->latest('id');
    }
}
