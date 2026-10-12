<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SunuPayIn extends Model
{
    public const STATUS_DECLARED = 'declared';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_TPE = 'tpe';
    public const SOURCE_BO = 'bo';

    public const METHOD_BANK = 'bank_deposit';
    public const METHOD_CASH_OFFICE = 'cash_office';

    protected $fillable = [
        'business_unit_id',
        'agent_id',
        'terminal_id',
        'declared_amount',
        'method',
        'bank_reference',
        'deposit_slip_path',
        'declared_at',
        'status',
        'confirmed_by',
        'confirmed_at',
        'confirmed_amount',
        'variance',
        'notes',
        'client_reference',
        'source',
        'deposited_at',
        'bank_name',
        'bank_account',
        'recorded_by',
    ];

    protected $casts = [
        'declared_amount' => 'decimal:2',
        'confirmed_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'declared_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'deposited_at' => 'datetime',
    ];

    protected $appends = ['reference'];

    /** Number printed on the TPE deposit receipt and shown in the web list (VER-000012). */
    public function getReferenceAttribute(): ?string
    {
        return $this->id ? sprintf('VER-%06d', $this->id) : null;
    }

    /** Office note given on reject (stored in notes after "REJECTED: "). */
    public function rejectNote(): ?string
    {
        if ($this->status !== self::STATUS_REJECTED || ! $this->notes) {
            return null;
        }
        $pos = strrpos($this->notes, 'REJECTED: ');

        return $pos === false ? null : trim(substr($this->notes, $pos + 10));
    }

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

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
