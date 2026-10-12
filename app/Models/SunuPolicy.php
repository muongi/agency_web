<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SunuPolicy extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_LAPSED = 'lapsed';
    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'business_unit_id',
        'client_id',
        'insurance_product_id',
        'proposal_id',
        'policy_number',
        'sunulife_policy_id',
        'status',
        'effective_date',
        'premium_amount',
        'premium_frequency',
        'next_due_date',
        'outstanding_balance',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'next_due_date' => 'date',
        'premium_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InsuranceProduct::class, 'insurance_product_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(SunuProposal::class, 'proposal_id');
    }

    public function premiums(): HasMany
    {
        return $this->hasMany(SunuPremiumCollection::class, 'policy_id');
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_policy_links', 'policy_id', 'card_id')
            ->withPivot('slot_index')
            ->withTimestamps();
    }
}
