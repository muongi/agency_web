<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Card extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'account_id',
        'card_number',
        'card_serial',
        'prefix',
        'status',
        'expiration_date',
        'daily_transaction_limit',
        'daily_withdrawal_limit',
        'pin_hash',
        'issued_at',
        'activated_at',
        'written_at',
        'written_by',
        'blocked_at',
        'blocked_reason',
        'replaced_by_card_id',
        'replacement_reason',
        'replaced_at',
        'description',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'daily_transaction_limit' => 'decimal:2',
        'daily_withdrawal_limit' => 'decimal:2',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'written_at' => 'datetime',
        'blocked_at' => 'datetime',
        'replaced_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('card');
    }

    // Relationships
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /** SUNU proposals this card is linked to (sunu_proposals.card_id). */
    public function sunuProposals()
    {
        return $this->hasMany(SunuProposal::class, 'card_id');
    }

    /** SUNU policies this card is linked to (mirror of SunuPolicy::cards()). */
    public function sunuPolicies()
    {
        return $this->belongsToMany(SunuPolicy::class, 'card_policy_links', 'card_id', 'policy_id')
            ->withPivot('slot_index')
            ->withTimestamps();
    }

    /** SUNU customer behind the card: first linked policy's client, else first proposal's. */
    public function sunuClient(): ?Client
    {
        return $this->sunuPolicies->first()?->client ?? $this->sunuProposals->first()?->client;
    }

    public function replacedByCard()
    {
        return $this->belongsTo(Card::class, 'replaced_by_card_id');
    }

    public function replacedCard()
    {
        return $this->hasOne(Card::class, 'replaced_by_card_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere('expiration_date', '<', now());
    }

    public function scopeForBusinessUnit($query, $businessUnitId)
    {
        return $query->where('business_unit_id', $businessUnitId);
    }

    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByCardSerial($query, $cardSerial)
    {
        return $query->where('card_serial', $cardSerial);
    }

    public function scopeUnassigned($query)
    {
        return $query->where('status', 'unassigned');
    }

    // Helper Methods
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isInactive()
    {
        return $this->status === 'inactive';
    }

    public function isBlocked()
    {
        return $this->status === 'blocked';
    }

    public function isExpired()
    {
        return $this->status === 'expired' || $this->expiration_date < now();
    }

    public function isReplaced()
    {
        return $this->status === 'replaced';
    }

    public function canBeActivated()
    {
        return $this->status === 'inactive' && !$this->isExpired();
    }

    public function canBeBlocked()
    {
        return in_array($this->status, ['active', 'inactive']);
    }

    public function canBeReplaced()
    {
        return !$this->isReplaced();
    }

    public function setPin($pin)
    {
        $this->pin_hash = Hash::make($pin);
        $this->save();
    }

    public function verifyPin($pin)
    {
        if (!$this->pin_hash) {
            return false;
        }
        return Hash::check($pin, $this->pin_hash);
    }

    public function activate()
    {
        if ($this->canBeActivated()) {
            $this->status = 'active';
            $this->activated_at = now();
            $this->save();
            return true;
        }
        return false;
    }

    public function block($reason = null)
    {
        if ($this->canBeBlocked()) {
            $this->status = 'blocked';
            $this->blocked_at = now();
            $this->blocked_reason = $reason;
            $this->save();
            return true;
        }
        return false;
    }

    public function replace($newCardId, $reason = null)
    {
        if ($this->canBeReplaced()) {
            $this->status = 'replaced';
            $this->replaced_by_card_id = $newCardId;
            $this->replacement_reason = $reason;
            $this->replaced_at = now();
            $this->save();
            return true;
        }
        return false;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (!$card->issued_at) {
                $card->issued_at = now();
            }
        });

        // Auto-update status to expired if expiration_date has passed
        static::saving(function ($card) {
            if ($card->expiration_date && $card->expiration_date < now() && $card->status !== 'replaced') {
                $card->status = 'expired';
            }
        });
    }
}
