<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Collector request (from the TPE) to cancel or modify one of his premiums / subscriptions.
 * pending → approved | rejected (decision) → done (correction applied or closed by staff).
 */
class SunuCorrectionRequest extends Model
{
    public const TARGET_PREMIUM = 'premium';
    public const TARGET_PROPOSAL = 'proposal';

    public const TYPE_CANCEL = 'cancel';
    public const TYPE_MODIFY = 'modify';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DONE = 'done';

    public const REASONS = ['wrong_amount', 'wrong_policy', 'wrong_customer', 'duplicate', 'identity_error', 'other'];

    /** Statuses that block a second request on the same operation. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED];

    protected $fillable = [
        'business_unit_id',
        'reference',
        'target_type',
        'target_id',
        'target_reference',
        'request_type',
        'reason_code',
        'details',
        'proposed_changes',
        'status',
        'requested_by_agent_id',
        'terminal_id',
        'device_serial',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_note',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'client_reference',
    ];

    protected $casts = [
        'proposed_changes' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'requested_by_agent_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SunuCorrectionEvent::class, 'correction_request_id')->orderBy('id');
    }

    public function premium(): BelongsTo
    {
        return $this->belongsTo(SunuPremiumCollection::class, 'target_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(SunuProposal::class, 'target_id');
    }

    /** The premium or proposal this request is about. */
    public function target(): ?Model
    {
        return $this->target_type === self::TARGET_PREMIUM ? $this->premium : $this->proposal;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeForTarget($query, string $type, int $id)
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }
}
