<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SunuProposal extends Model
{
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_TRANSMITTED = 'transmitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    /** Cancelled after an approved correction request (never paid, no policy). */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_unit_id',
        'agent_id',
        'terminal_id',
        'client_id',
        'card_id',
        'insurance_product_id',
        'proposal_number',
        'status',
        'subscribed_at',
        'latitude',
        'longitude',
        'last_name',
        'first_names',
        'phone',
        'form_data',
        'proposal_image_path',
        'id_document_front_path',
        'id_document_back_path',
        'client_reference',
        'sunulife_policy_number',
        'identity_mismatch',
        'id_documents_source_proposal_id',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'form_data' => 'array',
        'identity_mismatch' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InsuranceProduct::class, 'insurance_product_id');
    }

    public function policy(): HasOne
    {
        return $this->hasOne(SunuPolicy::class, 'proposal_id');
    }

    public function premiums(): HasMany
    {
        return $this->hasMany(SunuPremiumCollection::class, 'proposal_id');
    }

    /**
     * Adds `first_premium_paid` (0/1): a valid premium on the proposal, or on the policy created
     * from it (premiums collected by policy after acceptance). Subscription = complete.
     */
    public function scopeWithFirstPremiumPaid($query)
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->getTable().'.*');
        }

        return $query->selectRaw('exists('.self::PAID_SQL.') as first_premium_paid');
    }

    /** Only paid (true) / unpaid (false) subscriptions. */
    public function scopeFirstPremiumPaid($query, bool $paid = true)
    {
        return $query->whereRaw(($paid ? '' : 'not ').'exists('.self::PAID_SQL.')');
    }

    protected const PAID_SQL = "select 1 from sunu_premium_collections pc where pc.status != 'cancelled'"
        .' and (pc.proposal_id = sunu_proposals.id'
        .' or pc.policy_id in (select sp.id from sunu_policies sp where sp.proposal_id = sunu_proposals.id))';

    public function isFirstPremiumPaid(): bool
    {
        if (array_key_exists('first_premium_paid', $this->attributes)) {
            return (bool) $this->attributes['first_premium_paid'];
        }

        return $this->premiums()->valid()->exists()
            || ($this->policy && $this->policy->premiums()->valid()->exists());
    }

    /** Proposal whose CNIB photos this one reuses (same customer, other product). */
    public function idDocumentsSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_documents_source_proposal_id');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(SunuCorrectionRequest::class, 'target_id')
            ->where('target_type', SunuCorrectionRequest::TARGET_PROPOSAL)->latest('id');
    }
}
